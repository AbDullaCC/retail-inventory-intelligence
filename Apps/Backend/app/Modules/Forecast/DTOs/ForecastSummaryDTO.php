<?php

declare(strict_types=1);

namespace App\Modules\Forecast\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Store-wide forward view: per-day expected demand (and worst case) summed
 * across every freshly forecasted product — the dashboard's "what happens
 * next" chart and projected-revenue KPI.
 */
final class ForecastSummaryDTO extends BaseData
{
    /**
     * @param  array<string, int>  $modelMix  model name => product count
     * @param  list<array{date: string, mean: float, hi_90: float}>  $daily
     */
    public function __construct(
        public readonly int $forecastedCount,
        public readonly float $projectedUnits30d,
        public readonly float $projectedRevenue30d,
        public readonly array $modelMix,
        public readonly ?string $generatedAt,
        public readonly array $daily,
    ) {}

    public function toArray(): array
    {
        return [
            'forecasted_count' => $this->forecastedCount,
            'projected_units_30d' => $this->projectedUnits30d,
            'projected_revenue_30d' => $this->projectedRevenue30d,
            'model_mix' => $this->modelMix,
            'generated_at' => $this->generatedAt,
            'daily' => $this->daily,
        ];
    }
}
