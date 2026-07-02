<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Mappers;

use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Forecast\DTOs\ProductForecastDTO;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Product\Models\Product;

/**
 * The only place ProductForecast rows become snapshots/DTOs.
 */
final class ForecastMapper
{
    public function toSnapshot(ProductForecast $forecast): ForecastSnapshot
    {
        return new ForecastSnapshot(
            expectedDailyDemand: $forecast->expected_daily_demand,
            demandOverLeadTime: $forecast->demand_over_lead_time,
            p90DemandOverLeadTime: $forecast->p90_demand_over_lead_time,
            demandLeadPlusCoverage: $forecast->demand_lead_plus_coverage,
            dailyMeans: array_map(static fn (array $point): float => (float) $point['mean'], $forecast->daily_forecast),
            actualsLast28d: $forecast->actuals_last_28d,
            modelUsed: $forecast->model_used,
            generatedAt: $forecast->generated_at->toDateTimeImmutable(),
            horizonDays: $forecast->horizon_days,
            leadTimeDays: $forecast->lead_time_days,
        );
    }

    /**
     * @param  list<array{date: string, qty: int}>  $history
     */
    public function toChartDTO(Product $product, ?ProductForecast $forecast, array $history): ProductForecastDTO
    {
        return new ProductForecastDTO(
            productId: $product->id,
            sku: $product->sku,
            name: $product->name,
            generatedAt: $forecast?->generated_at->format('c'),
            modelUsed: $forecast?->model_used,
            horizonDays: $forecast?->horizon_days,
            history: $history,
            forecast: $forecast->daily_forecast ?? [],
        );
    }
}
