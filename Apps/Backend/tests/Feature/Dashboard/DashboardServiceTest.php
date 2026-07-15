<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function movement(int $productId, StockMovementType $type, int $qty, int $daysAgo): void
    {
        /** @var StockMovement $m */
        $m = StockMovement::query()->create([
            'product_id' => $productId,
            'user_id' => null,
            'type' => $type,
            'quantity' => $qty,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'reason' => 'test',
        ]);

        $when = Carbon::now()->subDays($daysAgo);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);
    }

    public function test_summary_aggregates_inventory_kpis(): void
    {
        $category = Category::factory()->create();

        // Sells ~5/day with only 10 on hand → urgent reorder verdict.
        $seller = Product::factory()->create([
            'category_id' => $category->id, 'sku' => 'SELL-1', 'quantity' => 10, 'reorder_level' => 0, 'price' => 2.0, 'is_active' => true,
        ]);
        $this->movement($seller->id, StockMovementType::Out, 40, 2);
        $this->movement($seller->id, StockMovementType::Out, 30, 5);

        // Below its manual reorder_level but with zero sales — must NOT appear
        // in the alert: the dashboard is driven by intelligence verdicts, not
        // the manual minimum.
        Product::factory()->create([
            'category_id' => $category->id, 'quantity' => 5, 'reorder_level' => 10, 'price' => 3.0, 'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id, 'quantity' => 0, 'reorder_level' => 10, 'price' => 4.0, 'is_active' => false,
        ]);

        $summary = app(DashboardServiceInterface::class)->summary()->toArray();

        $this->assertSame(3, $summary['total_products']);
        $this->assertSame(2, $summary['active_products']);
        $this->assertSame(1, $summary['total_categories']);
        $this->assertSame(1, $summary['reorder_count']);
        $this->assertSame(1, $summary['urgent_count']);
        $this->assertSame(1, $summary['out_of_stock_count']);
        $this->assertSame(15, $summary['total_stock_units']);
        $this->assertSame(35.0, $summary['total_stock_value']);

        $this->assertCount(1, $summary['reorder_products']);
        $this->assertSame('SELL-1', $summary['reorder_products'][0]['sku']);
        $this->assertTrue($summary['reorder_products'][0]['is_urgent']);
    }
}
