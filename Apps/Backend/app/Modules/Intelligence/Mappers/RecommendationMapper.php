<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Mappers;

use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Intelligence\DTOs\RecommendationMetrics;
use App\Modules\Product\Models\Product;

/**
 * The only place a Product model + computed metrics become a RecommendationDTO.
 */
final class RecommendationMapper
{
    public function toDTO(
        Product $product,
        RecommendationMetrics $metrics,
        bool $leadTimeIsDefault,
        bool $unitCostIsDefault,
    ): RecommendationDTO {
        $categoryName = $product->relationLoaded('category') && $product->category !== null
            ? $product->category->name
            : null;

        return new RecommendationDTO(
            productId: (int) $product->id,
            sku: $product->sku,
            name: $product->name,
            categoryName: $categoryName,
            isActive: (bool) $product->is_active,
            type: $metrics->type,
            currentStock: $metrics->currentStock,
            salesVelocity: $metrics->salesVelocity,
            daysOfStockLeft: $metrics->daysOfStockLeft,
            leadTimeDays: $metrics->leadTimeDays,
            leadTimeIsDefault: $leadTimeIsDefault,
            unitCost: $metrics->unitCost,
            unitCostIsDefault: $unitCostIsDefault,
            needsReorder: $metrics->needsReorder,
            suggestedReorderQty: $metrics->suggestedReorderQty,
            reorderByDate: $metrics->reorderByDate,
            isUrgent: $metrics->isUrgent,
            isOverstocked: $metrics->isOverstocked,
            cashTiedUp: $metrics->cashTiedUp,
            reasoning: $metrics->reasoning,
            forecastSource: $metrics->forecastSource,
            modelUsed: $metrics->modelUsed,
            forecastGeneratedAt: $metrics->forecastGeneratedAt,
            projectedStockoutDate: $metrics->projectedStockoutDate,
            stockoutRisk: $metrics->stockoutRisk,
            demandTrendPct: $metrics->demandTrendPct,
            projectedUnits30d: $metrics->projectedUnits30d,
            projectedRevenue30d: $metrics->projectedRevenue30d,
        );
    }
}
