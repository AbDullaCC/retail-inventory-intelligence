<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Intelligence\Services\ReorderCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Forecast-mode behaviour of the calculator: a ForecastSnapshot replaces the
 * window-average demand estimate and unlocks the forecast-only insights.
 * (The no-snapshot fallback is pinned by ReorderCalculatorTest.)
 */
class ReorderCalculatorForecastTest extends TestCase
{
    private ReorderCalculator $calc;

    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ReorderCalculator;
        $this->today = new DateTimeImmutable('2026-07-01');
    }

    /**
     * @param  list<float>|null  $dailyMeans
     */
    private function snapshot(
        float $expectedDaily = 4.0,
        float $demandOverLead = 28.0,
        ?float $p90OverLead = 40.0,
        float $leadPlusCoverage = 84.0,
        ?array $dailyMeans = null,
        float $actualsLast28d = 100.0,
        string $model = 'AutoETS',
    ): ForecastSnapshot {
        return new ForecastSnapshot(
            expectedDailyDemand: $expectedDaily,
            demandOverLeadTime: $demandOverLead,
            p90DemandOverLeadTime: $p90OverLead,
            demandLeadPlusCoverage: $leadPlusCoverage,
            dailyMeans: $dailyMeans ?? array_fill(0, 28, $expectedDaily),
            actualsLast28d: $actualsLast28d,
            modelUsed: $model,
            generatedAt: new DateTimeImmutable('2026-07-01 06:00:00'),
            horizonDays: 28,
            leadTimeDays: 7,
        );
    }

    public function test_model_velocity_drives_the_decision_and_qty_includes_the_p90_safety_gap(): void
    {
        $m = $this->calc->analyze(
            currentStock: 24,
            unitsOutInWindow: 999, // must be ignored in forecast mode
            leadTimeDays: 7,
            unitCost: 20.0,
            today: $this->today,
            forecast: $this->snapshot(p90OverLead: 48.0),
        );

        $this->assertSame('model', $m->forecastSource);
        $this->assertSame('AutoETS', $m->modelUsed);
        $this->assertEqualsWithDelta(4.0, $m->salesVelocity, 1e-9, 'velocity = model expectation, not window units');
        $this->assertEqualsWithDelta(6.0, $m->daysOfStockLeft, 1e-9);
        $this->assertTrue($m->needsReorder);
        $this->assertTrue($m->isUrgent);
        // ceil(leadPlusCoverage 84 + safety (p90 48 - mean 28) 20) = 104
        $this->assertSame(104, $m->suggestedReorderQty);
        $this->assertStringContainsString('Forecast (AutoETS)', $m->reasoning);
    }

    public function test_null_p90_falls_back_to_safety_buffer_days_for_the_qty(): void
    {
        $m = $this->calc->analyze(
            currentStock: 24,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 20.0,
            today: $this->today,
            forecast: $this->snapshot(p90OverLead: null, model: 'TSB'),
        );

        // ceil(84 + velocity 4 * safetyBufferDays 3) = ceil(96) = 96
        $this->assertSame(96, $m->suggestedReorderQty);
        $this->assertSame('TSB', $m->modelUsed);
    }

    public function test_projected_stockout_date_walks_the_daily_curve(): void
    {
        $means = array_merge([1.0, 1.0, 1.0, 5.0], array_fill(0, 24, 5.0));

        $m = $this->calc->analyze(
            currentStock: 8,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(dailyMeans: $means),
        );

        // Cumulative: 1, 2, 3, 8 → stock exhausted on day index 3.
        $this->assertSame('2026-07-04', $m->projectedStockoutDate);
    }

    public function test_stockout_beyond_the_horizon_is_null(): void
    {
        $m = $this->calc->analyze(
            currentStock: 10_000,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(),
        );

        $this->assertNull($m->projectedStockoutDate);
        $this->assertSame('low', $m->stockoutRisk);
    }

    public function test_stockout_risk_tiers(): void
    {
        $risk = fn (int $stock): ?string => $this->calc->analyze(
            currentStock: $stock,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(demandOverLead: 28.0, p90OverLead: 40.0),
        )->stockoutRisk;

        $this->assertSame('high', $risk(20), 'expected lead-time demand alone exhausts stock');
        $this->assertSame('medium', $risk(30), 'only the p90 worst case exhausts stock');
        $this->assertSame('low', $risk(50), 'covered even in the worst case');
    }

    public function test_demand_trend_compares_forecast_against_recent_actuals(): void
    {
        // Next 28 days expected: 4 * 28 = 112 vs 56 actual → +100%.
        $m = $this->calc->analyze(
            currentStock: 100,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(actualsLast28d: 56.0),
        );
        $this->assertEqualsWithDelta(100.0, $m->demandTrendPct, 1e-9);

        // No recent actuals → no trend signal.
        $none = $this->calc->analyze(
            currentStock: 100,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(actualsLast28d: 0.0),
        );
        $this->assertNull($none->demandTrendPct);
    }

    public function test_dead_stock_verdict_when_the_model_expects_no_demand(): void
    {
        $m = $this->calc->analyze(
            currentStock: 40,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 2.0,
            today: $this->today,
            forecast: $this->snapshot(
                expectedDaily: 0.01,
                demandOverLead: 0.07,
                p90OverLead: null,
                leadPlusCoverage: 0.21,
                dailyMeans: array_fill(0, 28, 0.01),
                model: 'TSB',
            ),
        );

        $this->assertSame('dead_stock', $m->type);
        $this->assertFalse($m->needsReorder);
        $this->assertNull($m->stockoutRisk);
        // Nearly the full holding: max(0, 40 - 0.01*30) * 2 = 79.4
        $this->assertEqualsWithDelta(79.4, $m->cashTiedUp, 1e-9);
        $this->assertStringContainsString('demand has effectively stopped', $m->reasoning);
    }

    public function test_projected_units_and_revenue_use_the_selling_price(): void
    {
        $m = $this->calc->analyze(
            currentStock: 100,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 1.0,
            today: $this->today,
            forecast: $this->snapshot(),
            unitPrice: 10.0,
        );

        $this->assertEqualsWithDelta(120.0, $m->projectedUnits30d, 1e-9);
        $this->assertEqualsWithDelta(1200.0, $m->projectedRevenue30d, 1e-9);
    }

    public function test_without_a_snapshot_all_forecast_fields_stay_inert(): void
    {
        $m = $this->calc->analyze(
            currentStock: 24,
            unitsOutInWindow: 56,
            leadTimeDays: 7,
            unitCost: 20.0,
            today: $this->today,
        );

        $this->assertSame('fallback', $m->forecastSource);
        $this->assertNull($m->modelUsed);
        $this->assertNull($m->projectedStockoutDate);
        $this->assertNull($m->stockoutRisk);
        $this->assertNull($m->demandTrendPct);
        $this->assertNull($m->projectedUnits30d);
        $this->assertNull($m->projectedRevenue30d);
        $this->assertStringNotContainsString('Forecast (', $m->reasoning);
    }
}
