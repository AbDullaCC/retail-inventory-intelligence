<?php

declare(strict_types=1);

namespace Tests\Feature\Forecast;

use App\Modules\Category\Models\Category;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ForecastRunnerTest extends TestCase
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

    private function movement(int $productId, int $qty, int $daysAgo): void
    {
        /** @var StockMovement $m */
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

    private function product(): Product
    {
        return Product::factory()->create(['category_id' => Category::factory()->create()->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sidecarResponse(int $productId, float $mean = 2.0, ?float $hi = 4.0): array
    {
        $forecast = [];
        for ($i = 0; $i < 28; $i++) {
            $forecast[] = [
                'date' => Carbon::now()->addDays($i)->format('Y-m-d'),
                'mean' => $mean,
                'lo_90' => $hi === null ? null : 0.5,
                'hi_90' => $hi,
            ];
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'horizon_days' => 28,
            'results' => [[
                'product_id' => $productId,
                'model_used' => 'AutoETS',
                'history_days' => 10,
                'forecast' => $forecast,
                'expected_daily_demand' => $mean,
                'demand_over_lead_time' => $mean * 7,
                'p90_demand_over_lead_time' => $hi === null ? null : $hi * 7,
            ]],
        ];
    }

    public function test_run_stores_a_forecast_row_with_laravel_computed_aggregates(): void
    {
        $product = $this->product();
        $this->movement($product->id, 14, 10);
        $this->movement($product->id, 6, 3);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($this->sidecarResponse($product->id)),
        ]);

        $this->artisan('forecast:run')->assertExitCode(0);

        /** @var ProductForecast $row */
        $row = ProductForecast::query()->sole();
        $this->assertSame($product->id, $row->product_id);
        $this->assertSame('AutoETS', $row->model_used);
        $this->assertSame(28, $row->horizon_days);
        $this->assertEqualsWithDelta(2.0, $row->expected_daily_demand, 1e-9);
        // Laravel owns the coverage knob: sum of the first (7 + 14) daily means.
        $this->assertEqualsWithDelta(42.0, $row->demand_lead_plus_coverage, 1e-9);
        // Both movements fall inside the trailing 28 days of the series.
        $this->assertEqualsWithDelta(20.0, $row->actuals_last_28d, 1e-9);
        $this->assertCount(28, $row->daily_forecast);
    }

    public function test_run_sends_a_zero_filled_series_ending_yesterday(): void
    {
        $product = $this->product();
        $this->movement($product->id, 14, 10); // 2026-06-21
        $this->movement($product->id, 6, 3);   // 2026-06-28

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($this->sidecarResponse($product->id)),
        ]);

        $this->artisan('forecast:run')->assertExitCode(0);

        Http::assertSent(function (Request $request) use ($product): bool {
            if (! Str::endsWith($request->url(), '/forecast')) {
                return false;
            }

            $series = $request->data()['series'][0];
            $history = $series['history'];

            return $series['product_id'] === $product->id
                && count($history) === 10                        // 06-21 → 06-30, zero-filled
                && $history[0] === ['date' => '2026-06-21', 'qty' => 14]
                && $history[1] === ['date' => '2026-06-22', 'qty' => 0]
                && $history[7] === ['date' => '2026-06-28', 'qty' => 6]
                && end($history) === ['date' => '2026-06-30', 'qty' => 0];
        });
    }

    public function test_second_run_replaces_the_row_instead_of_appending(): void
    {
        $product = $this->product();
        $this->movement($product->id, 10, 2);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($this->sidecarResponse($product->id, mean: 3.0)),
        ]);

        $this->artisan('forecast:run')->assertExitCode(0);
        $this->artisan('forecast:run')->assertExitCode(0);

        $this->assertSame(1, ProductForecast::query()->count());
        $this->assertEqualsWithDelta(3.0, ProductForecast::query()->sole()->expected_daily_demand, 1e-9);
    }

    public function test_croston_null_intervals_are_stored_as_null(): void
    {
        $product = $this->product();
        $this->movement($product->id, 10, 2);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($this->sidecarResponse($product->id, hi: null)),
        ]);

        $this->artisan('forecast:run')->assertExitCode(0);

        $this->assertNull(ProductForecast::query()->sole()->p90_demand_over_lead_time);
    }

    public function test_product_whose_first_sale_is_today_is_skipped_not_sent_empty(): void
    {
        $withHistory = $this->product();
        $this->movement($withHistory->id, 10, 2);

        // First-ever sale TODAY: zero completed days of history — sending an
        // empty series would be rejected by the sidecar (min 1 point).
        $todayOnly = $this->product();
        $this->movement($todayOnly->id, 5, 0);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($this->sidecarResponse($withHistory->id)),
        ]);

        $this->artisan('forecast:run')->assertExitCode(0);

        Http::assertSent(function (Request $request) use ($todayOnly): bool {
            if (! Str::endsWith($request->url(), '/forecast')) {
                return true; // ignore the health check
            }
            foreach ($request->data()['series'] as $series) {
                if ($series['product_id'] === $todayOnly->id) {
                    return false; // must not be sent at all
                }
                if (count($series['history']) === 0) {
                    return false; // no empty series, ever
                }
            }

            return true;
        });

        $this->assertSame(1, ProductForecast::query()->count(), 'only the product with history is forecast');
    }

    public function test_unreachable_sidecar_fails_gracefully(): void
    {
        $product = $this->product();
        $this->movement($product->id, 10, 2);

        Http::fake(static function (): never {
            throw new ConnectionException('Connection refused');
        });

        $this->artisan('forecast:run')->assertExitCode(1);
        $this->assertSame(0, ProductForecast::query()->count());
    }
}
