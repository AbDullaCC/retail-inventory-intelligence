<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * The aggregate intelligence view: headline counts + total cash tied up, plus
 * the full list of per-product recommendations.
 */
final class RecommendationsSummaryDTO extends BaseData
{
    /**
     * @param  array<int, RecommendationDTO>  $recommendations
     */
    public function __construct(
        public readonly int $reorderCount,
        public readonly int $overstockCount,
        public readonly int $healthyCount,
        public readonly float $totalCashTiedUp,
        public readonly int $velocityWindowDays,
        public readonly int $defaultLeadTimeDays,
        public readonly string $generatedAt,
        public readonly array $recommendations,
        public readonly int $deadStockCount = 0,
        public readonly float $deadStockCashRecoverable = 0.0,
        public readonly int $forecastedCount = 0,
        public readonly ?float $projectedRevenue30d = null,
    ) {}

    public function toArray(): array
    {
        return [
            'reorder_count' => $this->reorderCount,
            'overstock_count' => $this->overstockCount,
            'healthy_count' => $this->healthyCount,
            'dead_stock_count' => $this->deadStockCount,
            'dead_stock_cash_recoverable' => $this->deadStockCashRecoverable,
            'forecasted_count' => $this->forecastedCount,
            'projected_revenue_30d' => $this->projectedRevenue30d,
            'total_cash_tied_up' => $this->totalCashTiedUp,
            'velocity_window_days' => $this->velocityWindowDays,
            'default_lead_time_days' => $this->defaultLeadTimeDays,
            'generated_at' => $this->generatedAt,
            'recommendations' => array_map(static fn (RecommendationDTO $r) => $r->toArray(), $this->recommendations),
        ];
    }
}
