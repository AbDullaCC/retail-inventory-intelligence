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

class DashboardSalesByCategoryTest extends TestCase
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

    public function test_ranks_categories_by_units_sold_within_the_window(): void
    {
        $kitchen = Category::factory()->create(['name' => 'Kitchen']);
        $decor = Category::factory()->create(['name' => 'Décor']);
        $quiet = Category::factory()->create(['name' => 'Quiet']);

        $pan = Product::factory()->create(['category_id' => $kitchen->id, 'price' => 2.00]);
        $whisk = Product::factory()->create(['category_id' => $kitchen->id, 'price' => 3.00]);
        $vase = Product::factory()->create(['category_id' => $decor->id, 'price' => 4.00]);
        Product::factory()->create(['category_id' => $quiet->id]);

        // Kitchen: 30 + 12 = 42 units inside the window.
        $this->movement($pan->id, StockMovementType::Out, 30, 2);
        $this->movement($whisk->id, StockMovementType::Out, 12, 5);
        // Noise that must not count: outside the window, restocks, adjustments.
        $this->movement($pan->id, StockMovementType::Out, 500, 10);
        $this->movement($whisk->id, StockMovementType::In, 999, 1);
        $this->movement($whisk->id, StockMovementType::Adjustment, 999, 1);

        // Décor: 20 units.
        $this->movement($vase->id, StockMovementType::Out, 20, 3);

        $rows = app(DashboardServiceInterface::class)->salesByCategory(7);

        $this->assertSame(['Kitchen', 'Décor'], array_column($rows, 'category_name'));
        $this->assertSame([42, 20], array_column($rows, 'units_sold'));
        // 30 × $2.00 + 12 × $3.00 — revenue at current prices.
        $this->assertSame(96.0, $rows[0]['revenue']);
        $this->assertSame(80.0, $rows[1]['revenue']);
        $this->assertSame($kitchen->id, $rows[0]['category_id']);
    }
}
