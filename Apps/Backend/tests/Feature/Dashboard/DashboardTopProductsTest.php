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

class DashboardTopProductsTest extends TestCase
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

    public function test_ranks_products_by_units_sold_within_the_window(): void
    {
        $category = Category::factory()->create();
        $runner = Product::factory()->create(['category_id' => $category->id, 'name' => 'Runner-up', 'price' => 2.50]);
        $winner = Product::factory()->create(['category_id' => $category->id, 'name' => 'Winner', 'price' => 4.00]);
        $quiet = Product::factory()->create(['category_id' => $category->id, 'name' => 'Quiet']);

        // Winner: 30 units inside the window; sales outside must not count.
        $this->movement($winner->id, StockMovementType::Out, 10, 0);
        $this->movement($winner->id, StockMovementType::Out, 20, 6);
        $this->movement($winner->id, StockMovementType::Out, 500, 10); // outside 7d

        // Runner-up: 12 units; restocks and adjustments must not count.
        $this->movement($runner->id, StockMovementType::Out, 12, 3);
        $this->movement($runner->id, StockMovementType::In, 999, 1);
        $this->movement($runner->id, StockMovementType::Adjustment, 999, 1);

        // Quiet: no sales at all — must not appear.

        $top = app(DashboardServiceInterface::class)->topProducts(days: 7, limit: 5);

        $this->assertSame(['Winner', 'Runner-up'], array_column($top, 'name'));
        $this->assertSame([30, 12], array_column($top, 'units_sold'));
        $this->assertSame(120.0, $top[0]['revenue']); // 30 × 4.00 at current price
        $this->assertSame($winner->id, $top[0]['product_id']);
    }

    public function test_limit_caps_the_ranking(): void
    {
        $category = Category::factory()->create();
        foreach ([5, 15, 25] as $index => $units) {
            $product = Product::factory()->create(['category_id' => $category->id, 'name' => "P{$index}"]);
            $this->movement($product->id, StockMovementType::Out, $units, 1);
        }

        $top = app(DashboardServiceInterface::class)->topProducts(days: 7, limit: 2);

        $this->assertCount(2, $top);
        $this->assertSame([25, 15], array_column($top, 'units_sold'));
    }
}
