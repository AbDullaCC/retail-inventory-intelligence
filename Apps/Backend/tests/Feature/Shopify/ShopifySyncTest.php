<?php

declare(strict_types=1);

namespace Tests\Feature\Shopify;

use App\Modules\Product\Models\Product;
use App\Modules\Shopify\Models\ShopifyProductMap;
use App\Modules\Shopify\Models\ShopifySyncState;
use App\Modules\Shopify\Services\Contracts\ShopifySyncServiceInterface;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-02 12:00:00');
        config()->set('services.shopify', [
            'domain' => 'test-store.myshopify.com',
            'token' => 'shpat_test',
            'version' => '2026-01',
            'timeout' => 5,
            'history_days' => 730,
            'throttle_delay_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function productsPayload(int $mugQty = 30, int $teeQty = 5): array
    {
        return ['data' => ['products' => [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'nodes' => [
                [
                    'id' => 'gid://shopify/Product/1',
                    'title' => 'Blue Mug',
                    'status' => 'ACTIVE',
                    'productType' => 'Kitchen',
                    'variants' => ['nodes' => [[
                        'id' => 'gid://shopify/ProductVariant/111',
                        'sku' => 'MUG-1',
                        'title' => 'Default Title',
                        'price' => '9.50',
                        'inventoryQuantity' => $mugQty,
                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/11', 'unitCost' => ['amount' => '4.20']],
                    ]]],
                ],
                [
                    'id' => 'gid://shopify/Product/2',
                    'title' => 'Logo Tee',
                    'status' => 'DRAFT',
                    'productType' => '',
                    'variants' => ['nodes' => [
                        [
                            'id' => 'gid://shopify/ProductVariant/222',
                            'sku' => '',
                            'title' => 'Small',
                            'price' => '15.00',
                            'inventoryQuantity' => 9,
                            'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/22', 'unitCost' => null],
                        ],
                        [
                            'id' => 'gid://shopify/ProductVariant/333',
                            'sku' => 'TEE-1',
                            'title' => 'Large',
                            'price' => '15.00',
                            'inventoryQuantity' => $teeQty,
                            'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/33', 'unitCost' => null],
                        ],
                    ]],
                ],
            ],
        ]]];
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array<string, mixed>
     */
    private function ordersPayload(array $orders): array
    {
        return ['data' => ['orders' => [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'nodes' => $orders,
        ]]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historicalOrders(): array
    {
        return [
            [
                'id' => 'gid://shopify/Order/1001', 'name' => '#1001',
                'createdAt' => '2026-06-20T10:00:00Z', 'cancelledAt' => null,
                'lineItems' => ['nodes' => [
                    ['quantity' => 3, 'variant' => ['id' => 'gid://shopify/ProductVariant/111']],
                ]],
            ],
            [
                'id' => 'gid://shopify/Order/1002', 'name' => '#1002',
                'createdAt' => '2026-06-25T12:00:00Z', 'cancelledAt' => '2026-06-25T13:00:00Z',
                'lineItems' => ['nodes' => [
                    ['quantity' => 50, 'variant' => ['id' => 'gid://shopify/ProductVariant/111']],
                ]],
            ],
            [
                'id' => 'gid://shopify/Order/1003', 'name' => '#1003',
                'createdAt' => '2026-07-01T09:00:00Z', 'cancelledAt' => null,
                'lineItems' => ['nodes' => [
                    ['quantity' => 2, 'variant' => ['id' => 'gid://shopify/ProductVariant/111']],
                    ['quantity' => 1, 'variant' => ['id' => 'gid://shopify/ProductVariant/333']],
                    ['quantity' => 5, 'variant' => ['id' => 'gid://shopify/ProductVariant/999']], // unmapped
                    ['quantity' => 4, 'variant' => null],                                          // deleted variant
                ]],
            ],
        ];
    }

    /**
     * Route the faked responses on the GraphQL query text; `$ordersBatches`
     * are consumed one per orders request.
     *
     * @param  array<string, mixed>  $products
     * @param  list<array<string, mixed>>  $ordersBatches
     */
    private function fakeShopify(array $products, array $ordersBatches): void
    {
        $ordersCall = 0;
        Http::fake(function (Request $request) use ($products, $ordersBatches, &$ordersCall) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, 'products(')) {
                return Http::response($products);
            }

            $batch = $ordersBatches[$ordersCall] ?? $this->ordersPayload([]);
            $ordersCall++;

            return Http::response($batch);
        });
    }

    private function sync(bool $productsOnly = false): array
    {
        return app(ShopifySyncServiceInterface::class)->sync($productsOnly);
    }

    public function test_initial_sync_imports_products_and_backfills_order_history(): void
    {
        $this->fakeShopify($this->productsPayload(), [$this->ordersPayload($this->historicalOrders())]);

        $stats = $this->sync();

        // Products: two variants with SKUs; the blank-SKU variant is skipped.
        $this->assertSame(2, $stats['products_created']);
        $this->assertSame(1, $stats['variants_skipped']);
        $this->assertTrue($stats['backfill']);

        $mug = Product::query()->where('sku', 'MUG-1')->sole();
        $this->assertSame('Blue Mug', $mug->name);
        $this->assertSame('Kitchen', $mug->category->name);
        $this->assertEqualsWithDelta(4.20, (float) $mug->cost, 1e-9, 'real unit cost from Shopify');
        $this->assertTrue($mug->is_active);

        $tee = Product::query()->where('sku', 'TEE-1')->sole();
        $this->assertSame('Logo Tee — Large', $tee->name);
        $this->assertSame('Shopify Imports', $tee->category->name, 'blank productType falls back');
        $this->assertNull($tee->cost);
        $this->assertFalse($tee->is_active, 'DRAFT products import as inactive');

        // Backfill: cancelled order skipped; unmapped/deleted variant lines skipped.
        $this->assertSame(2, $stats['orders_imported']);
        $this->assertSame(3, $stats['order_lines_imported']);
        $this->assertSame(0, $stats['order_lines_conflicted']);

        // Opening = current Shopify stock + everything sold: 30 + 5 = 35.
        $movements = StockMovement::query()->where('product_id', $mug->id)->orderBy('id')->get();
        $this->assertCount(3, $movements);
        $this->assertSame(['in', 'out', 'out'], $movements->pluck('type.value')->all());
        $this->assertSame(35, $movements[0]->quantity);
        $this->assertSame('Opening stock (Shopify backfill)', $movements[0]->reason);
        $this->assertSame('Shopify order #1001', $movements[1]->reason);
        // Chain: 0→35, 35→32, 32→30; final equals Shopify's current stock.
        $this->assertSame([0, 35, 32], $movements->pluck('quantity_before')->all());
        $this->assertSame([35, 32, 30], $movements->pluck('quantity_after')->all());
        $this->assertSame(30, $mug->refresh()->quantity);

        // Ledger dates are the ORDER dates (this is what the forecaster reads).
        $this->assertSame('2026-06-20 10:00:00', $movements[1]->created_at->format('Y-m-d H:i:s'));

        // Ledger ends at Shopify's stock → no reconciliation needed.
        $this->assertSame(0, $stats['inventory_adjustments']);
        $this->assertSame(5, $tee->refresh()->quantity);

        // Watermark = newest imported order.
        $this->assertSame('2026-07-01 09:00:00', ShopifySyncState::current()->orders_synced_until->format('Y-m-d H:i:s'));
    }

    public function test_second_sync_is_idempotent_and_incremental(): void
    {
        $this->fakeShopify($this->productsPayload(), [
            $this->ordersPayload($this->historicalOrders()),
            $this->ordersPayload([[
                'id' => 'gid://shopify/Order/1004', 'name' => '#1004',
                'createdAt' => '2026-07-02T08:00:00Z', 'cancelledAt' => null,
                'lineItems' => ['nodes' => [
                    ['quantity' => 4, 'variant' => ['id' => 'gid://shopify/ProductVariant/111']],
                ]],
            ]]),
        ]);

        $this->sync();
        $stats = $this->sync();

        // No duplicate products or maps.
        $this->assertSame(0, $stats['products_created']);
        $this->assertSame(2, $stats['products_updated']);
        $this->assertSame(2, ShopifyProductMap::query()->count());
        $this->assertFalse($stats['backfill']);

        // The new order went through the audited path, backdated to order time.
        $mug = Product::query()->where('sku', 'MUG-1')->sole();
        $orderMovement = StockMovement::query()
            ->where('product_id', $mug->id)
            ->where('reason', 'Shopify order #1004')
            ->sole();
        $this->assertSame(4, $orderMovement->quantity);
        $this->assertSame('2026-07-02 08:00:00', $orderMovement->created_at->format('Y-m-d H:i:s'));
        // 30 on hand − 4 sold = 26; Shopify fixture still says 30, so reconcile corrects it back.
        $this->assertSame(1, $stats['inventory_adjustments']);
        $this->assertSame(30, $mug->refresh()->quantity);

        $this->assertSame('2026-07-02 08:00:00', ShopifySyncState::current()->orders_synced_until->format('Y-m-d H:i:s'));
    }

    public function test_conflicting_order_is_skipped_and_reconciled(): void
    {
        $this->fakeShopify($this->productsPayload(), [
            $this->ordersPayload($this->historicalOrders()),
            $this->ordersPayload([[
                'id' => 'gid://shopify/Order/1005', 'name' => '#1005',
                'createdAt' => '2026-07-02T09:00:00Z', 'cancelledAt' => null,
                'lineItems' => ['nodes' => [
                    // Far more than local stock (30) — would drive the ledger negative.
                    ['quantity' => 999, 'variant' => ['id' => 'gid://shopify/ProductVariant/111']],
                ]],
            ]]),
        ]);

        $this->sync();
        $stats = $this->sync();

        $this->assertSame(1, $stats['order_lines_conflicted']);
        $this->assertSame(0, $stats['order_lines_imported']);
        // Local quantity still matches Shopify → no adjustment, nothing corrupted.
        $mug = Product::query()->where('sku', 'MUG-1')->sole();
        $this->assertSame(30, $mug->quantity);
        $this->assertSame(0, StockMovement::query()->where('quantity_after', '<', 0)->count());
    }

    public function test_products_only_flag_skips_orders_and_inventory(): void
    {
        $this->fakeShopify($this->productsPayload(), []);

        $stats = $this->sync(productsOnly: true);

        $this->assertSame(2, $stats['products_created']);
        $this->assertSame(0, $stats['orders_imported']);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertNull(ShopifySyncState::current()->orders_synced_until, 'backfill still pending');
        Http::assertNotSent(fn (Request $r) => str_contains((string) ($r->data()['query'] ?? ''), 'orders('));
    }

    public function test_throttled_responses_are_retried(): void
    {
        $throttled = ['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]];
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls, $throttled) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, 'products(')) {
                $calls++;

                return Http::response($calls === 1 ? $throttled : $this->productsPayload());
            }

            return Http::response($this->ordersPayload([]));
        });

        $stats = $this->sync();

        $this->assertSame(2, $calls, 'first products call throttled, second succeeded');
        $this->assertSame(2, $stats['products_created']);
    }

    public function test_unconfigured_command_fails_with_setup_instructions(): void
    {
        config()->set('services.shopify.domain', null);

        $this->artisan('shopify:sync')
            ->expectsOutputToContain('Shopify is not configured.')
            ->assertExitCode(1);
    }
}
