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
     * @param  'reorder'|'overstock'|'healthy'  $type
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
    ) {}
}
