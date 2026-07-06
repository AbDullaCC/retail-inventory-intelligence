<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Services;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Shopify\Models\ShopifyConnection;
use App\Modules\Shopify\Models\ShopifyProductMap;
use App\Modules\Shopify\Models\ShopifySyncState;
use App\Modules\Shopify\Services\Contracts\ShopifySyncServiceInterface;
use App\Modules\Shopify\Support\ShopifyClient;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Exceptions\InsufficientStockException;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pulls a connected Shopify store into the local catalogue and ledger.
 *
 * Products map one-to-one per variant (idempotent via shopify_product_maps).
 * The FIRST order sync backfills history as backdated ledger rows with a
 * computed opening balance — the sanctioned bulk path, same as the dataset
 * importer — so the forecasting models immediately have real merchant sales
 * to learn from. Subsequent syncs are incremental from a watermark and write
 * through StockService::adjust (then backdate), keeping the audited path.
 * Inventory is reconciled last: local on-hand is corrected to Shopify's via
 * an 'adjustment' movement whenever they disagree.
 */
final class ShopifySyncService implements ShopifySyncServiceInterface
{
    private const PAGE_SIZE = 50;

    private const FALLBACK_CATEGORY = 'Shopify Imports';

    private const PRODUCTS_QUERY = <<<'GRAPHQL'
    query($first: Int!, $cursor: String) {
      products(first: $first, after: $cursor) {
        pageInfo { hasNextPage endCursor }
        nodes {
          id
          title
          status
          productType
          variants(first: 50) {
            nodes {
              id
              sku
              title
              price
              inventoryQuantity
              inventoryItem { id unitCost { amount } }
            }
          }
        }
      }
    }
    GRAPHQL;

    private const ORDERS_QUERY = <<<'GRAPHQL'
    query($first: Int!, $cursor: String, $search: String) {
      orders(first: $first, after: $cursor, query: $search, sortKey: CREATED_AT) {
        pageInfo { hasNextPage endCursor }
        nodes {
          id
          name
          createdAt
          cancelledAt
          lineItems(first: 100) {
            nodes {
              quantity
              variant { id }
            }
          }
        }
      }
    }
    GRAPHQL;

    public function __construct(
        private readonly ShopifyClient $client,
        private readonly StockServiceInterface $stock,
    ) {}

    public function sync(bool $productsOnly = false): array
    {
        $stats = [
            'products_created' => 0,
            'products_updated' => 0,
            'variants_skipped' => 0,
            'orders_imported' => 0,
            'order_lines_imported' => 0,
            'order_lines_conflicted' => 0,
            'inventory_adjustments' => 0,
            'backfill' => false,
        ];

        $shopifyQuantities = $this->syncProducts($stats);

        if (! $productsOnly) {
            $this->syncOrders($stats, $shopifyQuantities);
            $this->reconcileInventory($stats, $shopifyQuantities);
        }

        ShopifySyncState::current()->update(['products_synced_at' => Carbon::now()]);
        ShopifyConnection::current()?->update([
            'last_synced_at' => Carbon::now(),
            'last_stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Import/refresh products. Returns Shopify's current on-hand quantity per
     * local product id (used by the backfill maths and the reconcile pass).
     *
     * @param  array<string, int|bool>  $stats
     * @return array<int, int>
     */
    private function syncProducts(array &$stats): array
    {
        $variantToProduct = ShopifyProductMap::query()->pluck('product_id', 'shopify_variant_id')->all();
        $quantities = [];

        foreach ($this->paginate(self::PRODUCTS_QUERY, 'products') as $node) {
            $categoryId = $this->categoryIdFor((string) ($node['productType'] ?? ''));
            $isActive = ($node['status'] ?? 'ACTIVE') === 'ACTIVE';

            foreach ($node['variants']['nodes'] ?? [] as $variant) {
                $sku = trim((string) ($variant['sku'] ?? ''));
                if ($sku === '') {
                    $stats['variants_skipped']++;

                    continue;
                }

                $attrs = [
                    'category_id' => $categoryId,
                    'name' => $this->productName((string) $node['title'], (string) ($variant['title'] ?? '')),
                    'price' => (float) ($variant['price'] ?? 0),
                    'cost' => isset($variant['inventoryItem']['unitCost']['amount'])
                        ? (float) $variant['inventoryItem']['unitCost']['amount']
                        : null,
                    'is_active' => $isActive,
                ];

                $variantId = (string) $variant['id'];
                $existingProductId = $variantToProduct[$variantId] ?? null;

                if ($existingProductId !== null) {
                    Product::query()->whereKey($existingProductId)->update($attrs);
                    $productId = (int) $existingProductId;
                    $stats['products_updated']++;
                } else {
                    $product = Product::query()->create($attrs + [
                        'sku' => $sku,
                        'description' => null,
                        'quantity' => 0,
                        'reorder_level' => 0,
                    ]);
                    ShopifyProductMap::query()->create([
                        'product_id' => $product->id,
                        'shopify_product_id' => (string) $node['id'],
                        'shopify_variant_id' => $variantId,
                        'shopify_inventory_item_id' => $variant['inventoryItem']['id'] ?? null,
                    ]);
                    $productId = (int) $product->id;
                    $variantToProduct[$variantId] = $productId;
                    $stats['products_created']++;
                }

                $quantities[$productId] = max(0, (int) ($variant['inventoryQuantity'] ?? 0));
            }
        }

        return $quantities;
    }

    /**
     * @param  array<string, int|bool>  $stats
     * @param  array<int, int>  $shopifyQuantities
     */
    private function syncOrders(array &$stats, array $shopifyQuantities): void
    {
        $state = ShopifySyncState::current();
        $backfill = $state->orders_synced_until === null;
        $stats['backfill'] = $backfill;

        $since = $backfill
            ? Carbon::now()->subDays((int) config('services.shopify.history_days'))->toImmutable()
            : $state->orders_synced_until;

        $variantToProduct = ShopifyProductMap::query()->pluck('product_id', 'shopify_variant_id')->all();

        /** @var list<array{product_id: int, qty: int, at: string, order: string}> $lines */
        $lines = [];
        $maxCreatedAt = null;

        $search = sprintf("created_at:>'%s'", $since->toIso8601String());
        foreach ($this->paginate(self::ORDERS_QUERY, 'orders', ['search' => $search]) as $order) {
            if (($order['cancelledAt'] ?? null) !== null) {
                continue;
            }

            $createdAt = Carbon::parse((string) $order['createdAt']);
            $maxCreatedAt = $maxCreatedAt === null ? $createdAt : $maxCreatedAt->max($createdAt);

            $imported = false;
            foreach ($order['lineItems']['nodes'] ?? [] as $line) {
                $variantId = $line['variant']['id'] ?? null;
                $productId = $variantId === null ? null : ($variantToProduct[(string) $variantId] ?? null);
                $qty = (int) ($line['quantity'] ?? 0);
                if ($productId === null || $qty <= 0) {
                    continue;
                }

                $lines[] = [
                    'product_id' => (int) $productId,
                    'qty' => $qty,
                    'at' => $createdAt->format('Y-m-d H:i:s'),
                    'order' => (string) ($order['name'] ?? $order['id']),
                ];
                $imported = true;
            }

            if ($imported) {
                $stats['orders_imported']++;
            }
        }

        usort($lines, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        if ($backfill) {
            $this->backfillLines($stats, $lines, $shopifyQuantities);
        } else {
            $this->applyLinesIncrementally($stats, $lines);
        }

        $state->update(['orders_synced_until' => $maxCreatedAt ?? $since]);
    }

    /**
     * First run: insert history as backdated ledger rows with a computed
     * opening balance (opening = Shopify's current stock + everything sold in
     * the window), so the chain ends exactly at today's real on-hand and is
     * never negative. Only products with an empty ledger are backfilled —
     * anything else is left to the incremental path and the reconcile pass.
     *
     * @param  array<string, int|bool>  $stats
     * @param  list<array{product_id: int, qty: int, at: string, order: string}>  $lines
     * @param  array<int, int>  $shopifyQuantities
     */
    private function backfillLines(array &$stats, array $lines, array $shopifyQuantities): void
    {
        $byProduct = [];
        foreach ($lines as $line) {
            $byProduct[$line['product_id']][] = $line;
        }

        $rows = [];
        $finalQuantities = [];

        foreach ($byProduct as $productId => $productLines) {
            $hasLedger = StockMovement::query()->where('product_id', $productId)->exists();
            if ($hasLedger) {
                $stats['order_lines_conflicted'] += count($productLines);

                continue;
            }

            $totalSold = array_sum(array_column($productLines, 'qty'));
            $shopifyQty = $shopifyQuantities[$productId] ?? 0;
            $opening = $shopifyQty + $totalSold;
            $openingAt = Carbon::parse($productLines[0]['at'])->subDay()->format('Y-m-d H:i:s');

            $balance = 0;
            $rows[] = $this->row($productId, StockMovementType::In, $opening, $balance, $opening, 'Opening stock (Shopify backfill)', $openingAt);
            $balance = $opening;

            foreach ($productLines as $line) {
                $rows[] = $this->row($productId, StockMovementType::Out, $line['qty'], $balance, $balance - $line['qty'], 'Shopify order '.$line['order'], $line['at']);
                $balance -= $line['qty'];
                $stats['order_lines_imported']++;
            }

            $finalQuantities[$productId] = $balance;
        }

        // Chronological ids across products, same as the dataset importer.
        usort($rows, static fn (array $a, array $b): int => [$a['created_at'], $a['product_id']] <=> [$b['created_at'], $b['product_id']]);

        DB::transaction(function () use ($rows, $finalQuantities): void {
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('stock_movements')->insert($chunk);
            }
            foreach ($finalQuantities as $productId => $quantity) {
                Product::query()->whereKey($productId)->update(['quantity' => $quantity]);
            }
        });
    }

    /**
     * Incremental runs write through the audited StockService path, then
     * backdate the row to the order's timestamp (the same pattern the demo
     * seeder uses). A drift conflict (order would drive stock negative) is
     * counted and left for the reconcile pass to correct.
     *
     * @param  array<string, int|bool>  $stats
     * @param  list<array{product_id: int, qty: int, at: string, order: string}>  $lines
     */
    private function applyLinesIncrementally(array &$stats, array $lines): void
    {
        foreach ($lines as $line) {
            try {
                $movement = $this->stock->adjust(
                    $line['product_id'],
                    new StockAdjustmentData(StockMovementType::Out, $line['qty'], 'Shopify order '.$line['order']),
                );
            } catch (InsufficientStockException) {
                $stats['order_lines_conflicted']++;

                continue;
            }

            StockMovement::query()->whereKey($movement->id)->update([
                'created_at' => $line['at'],
                'updated_at' => $line['at'],
            ]);
            $stats['order_lines_imported']++;
        }
    }

    /**
     * @param  array<string, int|bool>  $stats
     * @param  array<int, int>  $shopifyQuantities
     */
    private function reconcileInventory(array &$stats, array $shopifyQuantities): void
    {
        foreach ($shopifyQuantities as $productId => $shopifyQty) {
            /** @var Product|null $product */
            $product = Product::query()->find($productId);
            if ($product === null || (int) $product->quantity === $shopifyQty) {
                continue;
            }

            $this->stock->adjust(
                $productId,
                new StockAdjustmentData(StockMovementType::Adjustment, $shopifyQty, 'Shopify inventory sync'),
            );
            $stats['inventory_adjustments']++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $productId, StockMovementType $type, int $qty, int $before, int $after, string $reason, string $at): array
    {
        return [
            'product_id' => $productId,
            'user_id' => null,
            'type' => $type->value,
            'quantity' => $qty,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    private function categoryIdFor(string $productType): int
    {
        $name = trim($productType) !== '' ? trim($productType) : self::FALLBACK_CATEGORY;

        return (int) Category::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Imported from Shopify.'],
        )->id;
    }

    private function productName(string $productTitle, string $variantTitle): string
    {
        return $variantTitle !== '' && $variantTitle !== 'Default Title'
            ? $productTitle.' — '.$variantTitle
            : $productTitle;
    }

    /**
     * Cursor-paginate a GraphQL connection, yielding its nodes.
     *
     * @param  array<string, mixed>  $extraVariables
     * @return Generator<array<string, mixed>>
     */
    private function paginate(string $query, string $root, array $extraVariables = []): Generator
    {
        $cursor = null;
        do {
            $data = $this->client->query($query, $extraVariables + ['first' => self::PAGE_SIZE, 'cursor' => $cursor]);
            $connection = $data[$root] ?? ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []];

            foreach ($connection['nodes'] ?? [] as $node) {
                yield $node;
            }

            $cursor = $connection['pageInfo']['endCursor'] ?? null;
        } while (($connection['pageInfo']['hasNextPage'] ?? false) && $cursor !== null);
    }
}
