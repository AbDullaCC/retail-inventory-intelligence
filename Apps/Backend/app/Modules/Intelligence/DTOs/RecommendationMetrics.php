<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Intelligence\Services\ReorderCalculator;

/**
 * The pure output of {@see ReorderCalculator}.
 *
 * An immutable value object holding every computed metric for a single product.
 * It carries raw (unrounded) numbers — rounding happens only at display time.
 */
final class RecommendationMetrics
{
    /**
     * The forecast-sourced fields (trailing, defaulted) are null/'fallback'
     * when the calculator ran on the historical window average.
     *
     * @param  'reorder'|'overstock'|'healthy'|'dead_stock'  $type
     * @param  'model'|'fallback'  $forecastSource
     * @param  'high'|'medium'|'low'|null  $stockoutRisk
     */
    public function __construct(
        public readonly string $type,
        public readonly float $salesVelocity,
        public readonly ?float $daysOfStockLeft,
        public readonly bool $needsReorder,
        public readonly int $suggestedReorderQty,
        public readonly ?string $reorderByDate,
        public readonly bool $isUrgent,
        public readonly bool $isOverstocked,
        public readonly float $cashTiedUp,
        public readonly string $reasoning,
        public readonly int $currentStock,
        public readonly int $leadTimeDays,
        public readonly float $unitCost,
        public readonly string $forecastSource = 'fallback',
        public readonly ?string $modelUsed = null,
        public readonly ?string $forecastGeneratedAt = null,
        public readonly ?string $projectedStockoutDate = null,
        public readonly ?string $stockoutRisk = null,
        public readonly ?float $demandTrendPct = null,
        public readonly ?float $projectedUnits30d = null,
        public readonly ?float $projectedRevenue30d = null,
    ) {}
}
