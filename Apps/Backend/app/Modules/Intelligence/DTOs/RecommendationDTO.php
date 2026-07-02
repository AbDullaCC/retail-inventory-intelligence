<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Serialisable, per-product recommendation returned by the API. Combines the
 * product's identity with the computed {@see RecommendationMetrics}.
 */
final class RecommendationDTO extends BaseData
{
    /**
     * @param  'reorder'|'overstock'|'healthy'|'dead_stock'  $type
     * @param  'model'|'fallback'  $forecastSource
     * @param  'high'|'medium'|'low'|null  $stockoutRisk
     */
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $categoryName,
        public readonly bool $isActive,
        public readonly string $type,
        public readonly int $currentStock,
        public readonly float $salesVelocity,
        public readonly ?float $daysOfStockLeft,
        public readonly int $leadTimeDays,
        public readonly bool $leadTimeIsDefault,
        public readonly float $unitCost,
        public readonly bool $unitCostIsDefault,
        public readonly bool $needsReorder,
        public readonly int $suggestedReorderQty,
        public readonly ?string $reorderByDate,
        public readonly bool $isUrgent,
        public readonly bool $isOverstocked,
        public readonly float $cashTiedUp,
        public readonly string $reasoning,
        public readonly string $forecastSource = 'fallback',
        public readonly ?string $modelUsed = null,
        public readonly ?string $forecastGeneratedAt = null,
        public readonly ?string $projectedStockoutDate = null,
        public readonly ?string $stockoutRisk = null,
        public readonly ?float $demandTrendPct = null,
        public readonly ?float $projectedUnits30d = null,
        public readonly ?float $projectedRevenue30d = null,
    ) {}

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'name' => $this->name,
            'category_name' => $this->categoryName,
            'is_active' => $this->isActive,
            'type' => $this->type,
            'current_stock' => $this->currentStock,
            'sales_velocity' => $this->salesVelocity,
            'days_of_stock_left' => $this->daysOfStockLeft,
            'lead_time_days' => $this->leadTimeDays,
            'lead_time_is_default' => $this->leadTimeIsDefault,
            'unit_cost' => $this->unitCost,
            'unit_cost_is_default' => $this->unitCostIsDefault,
            'needs_reorder' => $this->needsReorder,
            'suggested_reorder_qty' => $this->suggestedReorderQty,
            'reorder_by_date' => $this->reorderByDate,
            'is_urgent' => $this->isUrgent,
            'is_overstocked' => $this->isOverstocked,
            'cash_tied_up' => $this->cashTiedUp,
            'reasoning' => $this->reasoning,
            'forecast_source' => $this->forecastSource,
            'model_used' => $this->modelUsed,
            'forecast_generated_at' => $this->forecastGeneratedAt,
            'projected_stockout_date' => $this->projectedStockoutDate,
            'stockout_risk' => $this->stockoutRisk,
            'demand_trend_pct' => $this->demandTrendPct,
            'projected_units_30d' => $this->projectedUnits30d,
            'projected_revenue_30d' => $this->projectedRevenue30d,
        ];
    }
}
