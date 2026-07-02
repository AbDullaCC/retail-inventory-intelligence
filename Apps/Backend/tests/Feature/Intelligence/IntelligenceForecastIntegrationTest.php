<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Modules\Category\Models\Category;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Intelligence ↔ Forecast wiring: a fresh stored forecast drives the numbers,
 * a stale (or missing) one falls back to the window-average formula.
 */
class IntelligenceForecastIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private IntelligenceServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
        $this->service = app(IntelligenceServiceInterface::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge(
            ['category_id' => Category::factory()->create()->id, 'quantity' => 24, 'cost' => 20.0, 'price' => 40.0],
            $attrs,
        ));
    }

    private function forecastRow(int $productId, string $generatedAt, float $expectedDaily = 4.0): ProductForecast
    {
        return ProductForecast::query()->create([
            'product_id' => $productId,
            'generated_at' => $generatedAt,
            'horizon_days' => 28,
            'history_days' => 200,
            'lead_time_days' => 7,
            'model_used' => 'AutoETS',
            'expected_daily_demand' => $expectedDaily,
            'demand_over_lead_time' => $expectedDaily * 7,
            'p90_demand_over_lead_time' => $expectedDaily * 7 * 1.5,
            'demand_lead_plus_coverage' => $expectedDaily * 21,
            'actuals_last_28d' => $expectedDaily * 28,
            'daily_forecast' => array_map(
                static fn (int $i): array => ['date' => Carbon::now()->addDays($i)->format('Y-m-d'), 'mean' => $expectedDaily, 'lo_90' => 0.0, 'hi_90' => $expectedDaily * 2],
                range(0, 27),
            ),
        ]);
    }

    public function test_a_fresh_forecast_drives_the_recommendation(): void
    {
        $product = $this->product();
        // Window sales that would give velocity 10/day — must be ignored.
        $this->sale($product->id, 140, 2);
        $this->forecastRow($product->id, '2026-07-01 06:00:00');

        $dto = $this->service->forProduct($product->id);

        $this->assertSame('model', $dto->forecastSource);
        $this->assertSame('AutoETS', $dto->modelUsed);
        $this->assertEqualsWithDelta(4.0, $dto->salesVelocity, 1e-9);
        $this->assertStringContainsString('Forecast (AutoETS)', $dto->reasoning);
        $this->assertNotNull($dto->projectedStockoutDate);
        $this->assertNotNull($dto->stockoutRisk);
        // 4/day * 30 * $40 price
        $this->assertEqualsWithDelta(4800.0, $dto->projectedRevenue30d, 1e-9);
    }

    public function test_a_stale_forecast_falls_back_to_the_window_average(): void
    {
        $product = $this->product();
        $this->sale($product->id, 56, 3); // velocity 4/day from the ledger
        $this->forecastRow($product->id, '2026-06-28 06:00:00', expectedDaily: 99.0); // 3 days old > 48h

        $dto = $this->service->forProduct($product->id);

        $this->assertSame('fallback', $dto->forecastSource);
        $this->assertNull($dto->modelUsed);
        $this->assertEqualsWithDelta(4.0, $dto->salesVelocity, 1e-9);
    }

    public function test_summary_aggregates_forecast_coverage_dead_stock_and_projected_revenue(): void
    {
        $forecasted = $this->product();
        $this->forecastRow($forecasted->id, '2026-07-01 06:00:00'); // healthy-ish reorder mix

        $dead = $this->product(['quantity' => 40, 'cost' => 2.0]);
        $this->forecastRow($dead->id, '2026-07-01 06:00:00', expectedDaily: 0.0);

        $unforecasted = $this->product();
        $this->sale($unforecasted->id, 28, 4);

        $summary = $this->service->recommendations();

        $this->assertSame(2, $summary->forecastedCount);
        $this->assertSame(1, $summary->deadStockCount);
        // Dead stock: max(0, 40 - 0) * 2 = 80 recoverable.
        $this->assertEqualsWithDelta(80.0, $summary->deadStockCashRecoverable, 1e-9);
        $this->assertNotNull($summary->projectedRevenue30d);
        $this->assertSame(
            ['dead_stock'],
            array_values(array_map(
                static fn ($r) => $r->type,
                array_filter($summary->recommendations, static fn ($r) => $r->productId === $dead->id),
            )),
        );
    }

    private function sale(int $productId, int $qty, int $daysAgo): void
    {
        $m = StockMovement::query()->create([
            'product_id' => $productId,
            'user_id' => null,
            'type' => StockMovementType::Out,
            'quantity' => $qty,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'reason' => 'test',
        ]);

        $when = Carbon::now()->subDays($daysAgo);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);
    }
}
