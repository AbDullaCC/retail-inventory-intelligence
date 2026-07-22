<?php

declare(strict_types=1);

namespace App\Modules\Forecast\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Demand projection over an arbitrary window (1–30 days from today), summed
 * from the per-product forecast curves: expected units and revenue, the top
 * products driving that revenue, and how many products are projected to run
 * out of stock inside the window.
 */
final class ForecastProjectionDTO extends BaseData
{
    /**
     * @param  list<array{product_id: int, name: string, units: float, revenue: float}>  $topProducts
     */
    public function __construct(
        public readonly int $days,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly int $forecastedCount,
        public readonly float $projectedUnits,
        public readonly float $projectedRevenue,
        public readonly int $projectedStockouts,
        public readonly array $topProducts,
    ) {}

    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'forecasted_count' => $this->forecastedCount,
            'projected_units' => $this->projectedUnits,
            'projected_revenue' => $this->projectedRevenue,
            'projected_stockouts' => $this->projectedStockouts,
            'top_products' => $this->topProducts,
        ];
    }
}
