<?php

declare(strict_types=1);

namespace Tests\Feature\Forecast;

use App\Modules\Category\Models\Category;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ForecastReader::projection — arbitrary-window demand/revenue rollup used by
 * the assistant's get_projected_demand tool.
 */
class ForecastProjectionTest extends TestCase
{
    use RefreshDatabase;

    private ForecastReaderInterface $reader;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
        $this->reader = app(ForecastReaderInterface::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(float $price, int $quantity): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => $price,
            'quantity' => $quantity,
        ]);
    }

    /**
     * @param  list<float>  $means
     */
    private function forecastRow(int $productId, array $means, float $flatDaily, string $generatedAt = '2026-07-01 06:00:00'): void
    {
        ProductForecast::query()->create([
            'product_id' => $productId,
            'generated_at' => $generatedAt,
            'horizon_days' => count($means),
            'history_days' => 200,
            'lead_time_days' => 7,
            'model_used' => 'AutoETS',
            'expected_daily_demand' => $flatDaily,
            'demand_over_lead_time' => $flatDaily * 7,
            'p90_demand_over_lead_time' => $flatDaily * 10,
            'demand_lead_plus_coverage' => $flatDaily * 21,
            'actuals_last_28d' => $flatDaily * 28,
            'daily_forecast' => array_map(
                static fn (float $mean, int $i): array => [
                    'date' => Carbon::now()->addDays($i)->format('Y-m-d'),
                    'mean' => $mean,
                    'lo_90' => 0.0,
                    'hi_90' => $mean * 2,
                ],
                $means,
                array_keys($means),
            ),
        ]);
    }

    public function test_sums_the_curve_weights_by_price_and_counts_window_stockouts(): void
    {
        $rising = $this->product(price: 10.0, quantity: 6);
        $this->forecastRow($rising->id, [1.0, 2.0, 3.0, 4.0, 5.0], flatDaily: 3.0);

        $flat = $this->product(price: 100.0, quantity: 500);
        $this->forecastRow($flat->id, array_fill(0, 5, 2.0), flatDaily: 2.0);

        $projection = $this->reader->projection(3, Carbon::now()->toDateTimeImmutable());

        $this->assertSame(3, $projection->days);
        $this->assertSame('2026-07-01', $projection->fromDate);
        $this->assertSame('2026-07-03', $projection->toDate);
        $this->assertSame(2, $projection->forecastedCount);
        // rising: 1+2+3 = 6 units; flat: 2×3 = 6 units
        $this->assertEqualsWithDelta(12.0, $projection->projectedUnits, 1e-9);
        $this->assertEqualsWithDelta(6 * 10.0 + 6 * 100.0, $projection->projectedRevenue, 1e-9);
        // rising exhausts its 6 on-hand by day 3; flat's 500 comfortably survive
        $this->assertSame(1, $projection->projectedStockouts);
        // top drivers ranked by revenue: flat ($600) before rising ($60)
        $this->assertSame($flat->id, $projection->topProducts[0]['product_id']);
        $this->assertSame($rising->id, $projection->topProducts[1]['product_id']);
    }

    public function test_pads_beyond_the_stored_horizon_with_the_flat_average(): void
    {
        $product = $this->product(price: 5.0, quantity: 1000);
        $this->forecastRow($product->id, array_fill(0, 5, 2.0), flatDaily: 2.0);

        $projection = $this->reader->projection(10, Carbon::now()->toDateTimeImmutable(), $product->id);

        // 5 curve days × 2 + 5 padded days × 2 = 20 units → $100 at price 5.
        $this->assertEqualsWithDelta(20.0, $projection->projectedUnits, 1e-9);
        $this->assertEqualsWithDelta(100.0, $projection->projectedRevenue, 1e-9);
    }

    public function test_excludes_stale_forecasts_and_clamps_the_window(): void
    {
        $stale = $this->product(price: 10.0, quantity: 50);
        $this->forecastRow($stale->id, array_fill(0, 5, 4.0), flatDaily: 4.0, generatedAt: '2026-06-27 06:00:00');

        $projection = $this->reader->projection(99, Carbon::now()->toDateTimeImmutable());

        $this->assertSame(30, $projection->days, 'window is clamped to 30 days');
        $this->assertSame(0, $projection->forecastedCount, 'stale rows are treated as absent');
        $this->assertEqualsWithDelta(0.0, $projection->projectedUnits, 1e-9);
    }
}
