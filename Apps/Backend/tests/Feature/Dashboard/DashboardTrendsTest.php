<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTrendsTest extends TestCase
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

    public function test_series_is_zero_filled_and_aggregated_per_type(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->movement($product->id, StockMovementType::Out, 5, 2);
        $this->movement($product->id, StockMovementType::Out, 3, 2);
        $this->movement($product->id, StockMovementType::In, 40, 1);
        $this->movement($product->id, StockMovementType::Adjustment, 9, 1); // counted in movements, not units
        $this->movement($product->id, StockMovementType::Out, 99, 45);      // outside the window

        $trends = app(DashboardServiceInterface::class)->trends(30);

        $this->assertSame(30, $trends->days);
        $this->assertCount(30, $trends->series, 'full calendar, zero-filled');

        $byDate = collect($trends->series)->keyBy('date');
        $this->assertSame(8, $byDate['2026-06-29']['units_out']);
        $this->assertSame(0, $byDate['2026-06-29']['units_in']);
        $this->assertSame(40, $byDate['2026-06-30']['units_in']);
        $this->assertSame(2, $byDate['2026-06-30']['movements']);
        // A day with no movements is a real zero, not a gap.
        $this->assertSame(0, $byDate['2026-06-15']['units_out']);
        $this->assertSame(0, $byDate['2026-06-15']['movements']);
    }

    public function test_product_filter_narrows_series_and_empties_categories(): void
    {
        $category = Category::factory()->create();
        $a = Product::factory()->create(['category_id' => $category->id, 'quantity' => 10, 'price' => 5]);
        $b = Product::factory()->create(['category_id' => $category->id]);

        $this->movement($a->id, StockMovementType::Out, 5, 1);
        $this->movement($b->id, StockMovementType::Out, 50, 1);

        $trends = app(DashboardServiceInterface::class)->trends(7, $a->id);

        $byDate = collect($trends->series)->keyBy('date');
        $this->assertSame(5, $byDate['2026-06-30']['units_out'], "only product A's sales");
        $this->assertSame([], $trends->categoryValues);
    }

    public function test_category_values_aggregate_stock_value(): void
    {
        $category = Category::factory()->create(['name' => 'Kitchen']);
        Product::factory()->create(['category_id' => $category->id, 'quantity' => 10, 'price' => 2.50]);
        Product::factory()->create(['category_id' => $category->id, 'quantity' => 4, 'price' => 10.00]);

        $trends = app(DashboardServiceInterface::class)->trends(7);

        $this->assertCount(1, $trends->categoryValues);
        $this->assertSame('Kitchen', $trends->categoryValues[0]['category_name']);
        $this->assertEqualsWithDelta(65.0, $trends->categoryValues[0]['stock_value'], 1e-9);
        $this->assertSame(14, $trends->categoryValues[0]['units']);
    }

    public function test_endpoint_validates_and_requires_auth(): void
    {
        $this->getJson('/api/dashboard/trends')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/dashboard/trends?days=500')->assertUnprocessable();
        $this->getJson('/api/dashboard/trends?product_id=999999')->assertUnprocessable();

        $response = $this->getJson('/api/dashboard/trends?days=7')->assertOk();
        $response->assertJsonPath('data.days', 7)->assertJsonCount(7, 'data.series');
    }
}
