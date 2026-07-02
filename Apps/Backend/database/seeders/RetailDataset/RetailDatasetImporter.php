<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the Online Retail II import: stream + aggregate the workbook,
 * curate the demo catalogue, synthesize categories/products, simulate the
 * replenishment ledger and bulk-insert it. Called only by the
 * inventory:import-retail command, which owns all console I/O.
 */
final class RetailDatasetImporter
{
    private const INSERT_CHUNK = 1000;

    public function __construct(
        private readonly XlsxSalesReader $reader,
        private readonly SkuCurator $curator,
        private readonly ProductSynthesizer $products,
        private readonly MovementGenerator $movements,
    ) {}

    /**
     * Phase 1 — stream the workbook into per-SKU daily aggregates.
     *
     * @return array{aggregates: array<string, array{days: array<string, array{sold: int, returned: int, orders: int}>, prices: array<string, int>, descriptions: array<string, int>, first: string, last: string, units: int}>, max_day: string, rows_read: int, rows_skipped: int, duplicates_dropped: int}
     */
    public function aggregate(string $path, ?callable $tick = null): array
    {
        $aggregates = [];
        $maxDay = '0000-00-00';
        $count = 0;

        foreach ($this->reader->rows($path) as [$sku, $day, $quantity, $price, $description]) {
            $agg = &$aggregates[$sku];
            $agg ??= ['days' => [], 'prices' => [], 'descriptions' => [], 'first' => $day, 'last' => $day, 'units' => 0];

            $bucket = &$agg['days'][$day];
            $bucket ??= ['sold' => 0, 'returned' => 0, 'orders' => 0];

            if ($quantity > 0) {
                $bucket['sold'] += $quantity;
                $bucket['orders']++;
                $priceKey = (string) $price;
                $agg['prices'][$priceKey] = ($agg['prices'][$priceKey] ?? 0) + 1;
                if ($description !== '') {
                    $agg['descriptions'][$description] = ($agg['descriptions'][$description] ?? 0) + 1;
                }
            } else {
                $bucket['returned'] += -$quantity;
            }

            $agg['units'] += $quantity;
            $agg['first'] = min($agg['first'], $day);
            $agg['last'] = max($agg['last'], $day);
            $maxDay = max($maxDay, $day);
            unset($agg, $bucket);

            if ($tick !== null && (++$count % 5000) === 0) {
                $tick($count);
            }
        }

        return [
            'aggregates' => $aggregates,
            'max_day' => $maxDay,
            'rows_read' => $this->reader->rowsRead,
            'rows_skipped' => $this->reader->rowsSkipped,
            'duplicates_dropped' => $this->reader->duplicatesDropped,
        ];
    }

    /**
     * Phase 2 — pick the demo catalogue.
     *
     * @param  array<string, array{days: array<string, array{sold: int, returned: int, orders: int}>, prices: array<string, int>, descriptions: array<string, int>, first: string, last: string, units: int}>  $aggregates
     * @return list<string>
     */
    public function curate(array $aggregates, string $maxDay, int $limit): array
    {
        return $this->curator->select($aggregates, $maxDay, $limit);
    }

    /**
     * Phase 3 — build categories, products and the movement ledger.
     *
     * @param  array<string, array{days: array<string, array{sold: int, returned: int, orders: int}>, prices: array<string, int>, descriptions: array<string, int>, first: string, last: string, units: int}>  $aggregates
     * @param  list<string>  $selected
     * @return array{products: int, categories: int, movements_in: int, movements_out: int, units_sold: int, first_day: string, last_day: string}
     */
    public function import(array $aggregates, array $selected, string $maxDay, ?callable $tick = null): array
    {
        $yesterday = new DateTimeImmutable('yesterday');
        $shiftDays = (int) (new DateTimeImmutable($maxDay))->diff($yesterday)->format('%a');

        return DB::transaction(function () use ($aggregates, $selected, $maxDay, $shiftDays, $tick): array {
            $categoryIds = [];
            $buffer = [];
            $closings = [];
            $stats = ['in' => 0, 'out' => 0, 'units' => 0];
            $firstDay = null;
            $lastDay = null;

            foreach ($selected as $index => $sku) {
                $agg = $aggregates[$sku];
                $attrs = $this->products->synthesize((string) $sku, $agg, $maxDay);

                $categoryIds[$attrs['category']] ??= Category::query()->firstOrCreate(
                    ['name' => $attrs['category']],
                    ['description' => $attrs['category'].' — imported demo catalogue.'],
                )->id;

                $generated = $this->movements->generate($agg['days'], $maxDay, $shiftDays, $this->personaTarget($index));

                $product = Product::query()->create([
                    'category_id' => $categoryIds[$attrs['category']],
                    'sku' => $attrs['sku'],
                    'name' => $attrs['name'],
                    'description' => null,
                    'price' => $attrs['price'],
                    'cost' => $attrs['cost'],
                    'reorder_level' => $attrs['reorder_level'],
                    'is_active' => true,
                    'quantity' => 0,
                ]);

                foreach ($generated['rows'] as $row) {
                    $buffer[] = [
                        'product_id' => $product->id,
                        'user_id' => null,
                        'type' => $row['type'],
                        'quantity' => $row['quantity'],
                        'quantity_before' => $row['quantity_before'],
                        'quantity_after' => $row['quantity_after'],
                        'reason' => $row['reason'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['created_at'],
                    ];
                    $stats[$row['type']]++;
                    if ($row['type'] === 'out') {
                        $stats['units'] += $row['quantity'];
                    }
                    $firstDay = $firstDay === null ? $row['created_at'] : min($firstDay, $row['created_at']);
                    $lastDay = $lastDay === null ? $row['created_at'] : max($lastDay, $row['created_at']);
                }

                $closings[$product->id] = ['closing' => $generated['closing'], 'opening_at' => $generated['opening_at']];

                if ($tick !== null) {
                    $tick($index + 1);
                }
            }

            // Globally chronological ids: dashboard/recent history sort by latest(id).
            usort($buffer, static fn (array $a, array $b): int => [$a['created_at'], $a['product_id']] <=> [$b['created_at'], $b['product_id']]);

            foreach (array_chunk($buffer, self::INSERT_CHUNK) as $chunk) {
                DB::table('stock_movements')->insert($chunk);
            }

            foreach ($closings as $productId => $state) {
                Product::query()->whereKey($productId)->update([
                    'quantity' => $state['closing'],
                    'created_at' => $state['opening_at'],
                ]);
            }

            return [
                'products' => count($selected),
                'categories' => count($categoryIds),
                'movements_in' => $stats['in'],
                'movements_out' => $stats['out'],
                'units_sold' => $stats['units'],
                'first_day' => (string) $firstDay,
                'last_day' => (string) $lastDay,
            ];
        });
    }

    /**
     * Deterministic closing-stock persona per curated index: ~20% understocked
     * (4–8 days cover → reorder verdicts, some urgent), ~10% overstocked
     * (75–90 days → overstock + cash tied up), ~70% healthy (15–40 days).
     */
    private function personaTarget(int $index): float
    {
        return match (true) {
            $index % 10 <= 1 => 4.0 + ($index % 5),
            $index % 10 === 2 => 75.0 + ($index % 16),
            default => 15.0 + ($index % 26),
        };
    }
}
