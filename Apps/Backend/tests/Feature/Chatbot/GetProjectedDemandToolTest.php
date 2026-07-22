<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Category\Models\Category;
use App\Modules\Chatbot\Services\Tools\GetProjectedDemandTool;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The get_projected_demand tool — the assistant's answer path for "how much
 * do we expect to sell/earn today / this week / in the next N days?".
 */
class GetProjectedDemandToolTest extends TestCase
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

    public function test_returns_rounded_projection_with_top_products(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 2.5,
            'quantity' => 100,
        ]);
        ProductForecast::query()->create([
            'product_id' => $product->id,
            'generated_at' => '2026-07-01 06:00:00',
            'horizon_days' => 28,
            'history_days' => 200,
            'lead_time_days' => 7,
            'model_used' => 'AutoETS',
            'expected_daily_demand' => 3.4,
            'demand_over_lead_time' => 23.8,
            'p90_demand_over_lead_time' => 35.0,
            'demand_lead_plus_coverage' => 71.4,
            'actuals_last_28d' => 95.2,
            'daily_forecast' => array_map(
                static fn (int $i): array => ['date' => Carbon::now()->addDays($i)->format('Y-m-d'), 'mean' => 3.4, 'lo_90' => 0.0, 'hi_90' => 6.8],
                range(0, 27),
            ),
        ]);

        $result = app(GetProjectedDemandTool::class)->build()->run(['days' => 5]);

        $this->assertSame(5, $result['days']);
        // 5 × 3.4 = 17 units → $42.50 at $2.50 — rounded for prose, not re-derived.
        $this->assertSame(17, $result['projected_units']);
        $this->assertSame(42.5, $result['projected_revenue']);
        $this->assertSame($product->id, $result['top_products'][0]['product_id']);
        $this->assertSame(0, $result['projected_stockouts']);

        $default = app(GetProjectedDemandTool::class)->build()->run([]);
        $this->assertSame(7, $default['days'], 'defaults to a 7-day window');
    }

    public function test_reports_an_error_payload_when_no_fresh_forecasts_exist(): void
    {
        $result = app(GetProjectedDemandTool::class)->build()->run(['days' => 7]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No fresh forecasts', $result['error']);
    }
}
