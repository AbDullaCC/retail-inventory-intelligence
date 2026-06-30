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
     * @param  'reorder'|'overstock'|'healthy'  $type
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
        ];
    }
}
