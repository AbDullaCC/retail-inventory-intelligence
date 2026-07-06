<?php

declare(strict_types=1);

namespace Tests\Feature\Forecast;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForecastApiTest extends TestCase
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

    private function product(): Product
    {
        return Product::factory()->create(['category_id' => Category::factory()->create()->id]);
    }

    private function storedForecast(int $productId, string $generatedAt): ProductForecast
    {
        return ProductForecast::query()->create([
            'product_id' => $productId,
            'generated_at' => $generatedAt,
            'horizon_days' => 28,
            'history_days' => 100,
            'lead_time_days' => 7,
            'model_used' => 'AutoETS',
            'expected_daily_demand' => 2.0,
            'demand_over_lead_time' => 14.0,
            'p90_demand_over_lead_time' => 28.0,
            'demand_lead_plus_coverage' => 42.0,
            'actuals_last_28d' => 60.0,
            'daily_forecast' => [['date' => '2026-07-01', 'mean' => 2.0, 'lo_90' => 0.5, 'hi_90' => 4.0]],
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/products/1/forecast')->assertUnauthorized();
    }

    public function test_returns_history_and_forecast_for_a_forecasted_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->product();

        $m = StockMovement::query()->create([
            'product_id' => $product->id, 'user_id' => null, 'type' => StockMovementType::Out,
            'quantity' => 5, 'quantity_before' => 10, 'quantity_after' => 5, 'reason' => 'test',
        ]);
        $when = Carbon::now()->subDays(3);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);

        $this->storedForecast($product->id, '2026-07-01 06:00:00');

        $response = $this->getJson("/api/products/{$product->id}/forecast")->assertOk();

        $response->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.model_used', 'AutoETS')
            ->assertJsonPath('data.horizon_days', 28)
            ->assertJsonCount(90, 'data.history')
            ->assertJsonCount(1, 'data.forecast');

        // History is zero-filled and ends yesterday.
        $history = $response->json('data.history');
        $this->assertSame('2026-06-30', end($history)['date']);
        $this->assertSame(5, $history[87]['qty']); // the sale 3 days ago (2026-06-28)
    }

    public function test_returns_history_with_empty_forecast_when_none_or_stale(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->product();

        // Stale row (3 days > 48h window) must be treated as absent.
        $this->storedForecast($product->id, '2026-06-28 06:00:00');

        $this->getJson("/api/products/{$product->id}/forecast")
            ->assertOk()
            ->assertJsonPath('data.model_used', null)
            ->assertJsonPath('data.generated_at', null)
            ->assertJsonCount(0, 'data.forecast')
            ->assertJsonCount(90, 'data.history');
    }

    public function test_unknown_product_is_a_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/999999/forecast')->assertNotFound();
    }

    public function test_summary_aggregates_fresh_forecasts_across_products(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $a = $this->product();
        $b = $this->product();
        $this->storedForecast($a->id, '2026-07-01 06:00:00');
        $this->storedForecast($b->id, '2026-07-01 06:00:00');
        // Stale — must not count.
        $this->storedForecast($this->product()->id, '2026-06-20 06:00:00');

        $response = $this->getJson('/api/forecast/summary')->assertOk();

        $response->assertJsonPath('data.forecasted_count', 2)
            ->assertJsonPath('data.model_mix.AutoETS', 2)
            ->assertJsonCount(1, 'data.daily');
        // Two products at 2.0 expected/day → 120 projected units over 30 days.
        $this->assertEqualsWithDelta(120.0, $response->json('data.projected_units_30d'), 1e-6);
        // Daily point sums both products' means: 2.0 + 2.0.
        $this->assertEqualsWithDelta(4.0, $response->json('data.daily.0.mean'), 1e-6);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/forecast/summary')->assertUnauthorized();
    }

    public function test_run_endpoint_triggers_a_forecast_refresh(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->product();

        $m = StockMovement::query()->create([
            'product_id' => $product->id, 'user_id' => null, 'type' => StockMovementType::Out,
            'quantity' => 5, 'quantity_before' => 10, 'quantity_after' => 5, 'reason' => 'test',
        ]);
        $when = Carbon::now()->subDays(2);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);

        $forecastPayload = [
            'generated_at' => Carbon::now()->toIso8601String(),
            'horizon_days' => 28,
            'results' => [[
                'product_id' => $product->id,
                'model_used' => 'SeasonalNaive',
                'history_days' => 3,
                'forecast' => array_map(
                    static fn (int $i): array => ['date' => Carbon::now()->addDays($i)->format('Y-m-d'), 'mean' => 1.0, 'lo_90' => 0.0, 'hi_90' => 2.0],
                    range(0, 27),
                ),
                'expected_daily_demand' => 1.0,
                'demand_over_lead_time' => 7.0,
                'p90_demand_over_lead_time' => 14.0,
            ]],
        ];

        Http::fake([
            '*/health' => Http::response(['status' => 'ok']),
            '*/forecast' => Http::response($forecastPayload),
        ]);

        $this->postJson('/api/forecast/run')
            ->assertOk()
            ->assertJsonPath('data.forecasted', 1);

        $this->assertSame(1, ProductForecast::query()->count());
    }

    public function test_run_endpoint_returns_503_when_the_sidecar_is_down(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake(static function (): never {
            throw new ConnectionException('Connection refused');
        });

        $this->postJson('/api/forecast/run')->assertStatus(503);
    }
}
