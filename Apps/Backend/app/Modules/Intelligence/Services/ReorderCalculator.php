<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Services;

use App\Modules\Intelligence\DTOs\RecommendationMetrics;
use App\Modules\Intelligence\Support\ReorderConfig;
use DateTimeImmutable;

/**
 * Pure inventory intelligence: turns a product snapshot into reorder/overstock
 * metrics. No database, no framework state — fully deterministic given its
 * inputs and a {@see ReorderConfig}, which is what makes it directly unit-testable.
 *
 * All intermediate maths stay in floats; rounding is confined to the human
 * readable reasoning string (and to callers rendering the values).
 */
final class ReorderCalculator
{
    /**
     * @param  int  $currentStock  on-hand units (from Product::quantity)
     * @param  int  $unitsOutInWindow  total units sold/out over the velocity window
     * @param  int  $leadTimeDays  supplier lead time
     * @param  float  $unitCost  cost per unit (for cash-tied-up)
     * @param  DateTimeImmutable  $today  reference "now" (injected for testability)
     */
    public function analyze(
        int $currentStock,
        int $unitsOutInWindow,
        int $leadTimeDays,
        float $unitCost,
        DateTimeImmutable $today,
        ?ReorderConfig $config = null,
    ): RecommendationMetrics {
        $config ??= ReorderConfig::defaults();

        // 1. sales velocity (avg units/day over the window)
        $salesVelocity = $unitsOutInWindow / $config->velocityWindowDays;

        // 2. days of stock left — null when there are no recent sales
        $daysOfStockLeft = $salesVelocity > 0.0
            ? $currentStock / $salesVelocity
            : null;

        // 3. needs reorder?
        $needsReorder = $daysOfStockLeft !== null
            && $daysOfStockLeft < ($leadTimeDays + $config->safetyBufferDays);

        // 4. suggested reorder quantity (cover lead time + coverage period)
        $suggestedReorderQty = (int) ceil($salesVelocity * ($leadTimeDays + $config->coveragePeriodDays));

        // 5. reorder-by date = today + floor(daysLeft - leadTime); <= 0 means order today (urgent)
        $reorderByDate = null;
        $isUrgent = false;
        if ($daysOfStockLeft !== null) {
            $offsetDays = (int) floor($daysOfStockLeft - $leadTimeDays);
            if ($offsetDays <= 0) {
                $isUrgent = true;
                $reorderByDate = $today->format('Y-m-d');
            } else {
                $reorderByDate = $today->modify("+{$offsetDays} days")->format('Y-m-d');
            }
        }

        // 6. overstocked?
        $isOverstocked = $daysOfStockLeft !== null
            && $daysOfStockLeft > $config->overstockThresholdDays;

        // 7. cash tied up in stock beyond what's needed
        $cashTiedUp = max(0.0, $currentStock - $salesVelocity * $config->neededStockDays) * $unitCost;

        // reorder takes priority over overstock (they are mutually exclusive anyway)
        $type = $needsReorder ? 'reorder' : ($isOverstocked ? 'overstock' : 'healthy');

        return new RecommendationMetrics(
            type: $type,
            salesVelocity: $salesVelocity,
            daysOfStockLeft: $daysOfStockLeft,
            needsReorder: $needsReorder,
            suggestedReorderQty: $suggestedReorderQty,
            reorderByDate: $reorderByDate,
            isUrgent: $isUrgent,
            isOverstocked: $isOverstocked,
            cashTiedUp: $cashTiedUp,
            reasoning: $this->reasoning($type, $salesVelocity, $daysOfStockLeft, $leadTimeDays, $suggestedReorderQty, $cashTiedUp, $config),
            currentStock: $currentStock,
            leadTimeDays: $leadTimeDays,
            unitCost: $unitCost,
        );
    }

    /**
     * Plain-language explanation. Rounding lives here (display only).
     *
     * @param  'reorder'|'overstock'|'healthy'  $type
     */
    private function reasoning(
        string $type,
        float $salesVelocity,
        ?float $daysOfStockLeft,
        int $leadTimeDays,
        int $suggestedReorderQty,
        float $cashTiedUp,
        ReorderConfig $config,
    ): string {
        if ($daysOfStockLeft === null) {
            return sprintf(
                'No sales in the last %d days — not enough recent demand to forecast a reorder.',
                $config->velocityWindowDays,
            );
        }

        $perWeek = (int) round($salesVelocity * 7);
        $days = (int) round($daysOfStockLeft);

        return match ($type) {
            'reorder' => sprintf(
                'Selling ~%d/week with ~%d days left. With a %d-day lead time, order %d units now to avoid a stockout.',
                $perWeek,
                $days,
                $leadTimeDays,
                $suggestedReorderQty,
            ),
            'overstock' => sprintf(
                '~%d days of stock at current sales. About $%s is tied up in excess inventory — consider a promotion.',
                $days,
                number_format($cashTiedUp, 2),
            ),
            default => sprintf(
                'Healthy — about %d days of cover selling ~%d/week. No action needed.',
                $days,
                $perWeek,
            ),
        };
    }
}
