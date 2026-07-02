<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Services;

use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Intelligence\DTOs\RecommendationMetrics;
use App\Modules\Intelligence\Support\ReorderConfig;
use DateTimeImmutable;

/**
 * Pure inventory intelligence: turns a product snapshot into reorder/overstock
 * metrics. No database, no framework state — fully deterministic given its
 * inputs and a {@see ReorderConfig}, which is what makes it directly unit-testable.
 *
 * When a {@see ForecastSnapshot} is provided (passed in, never fetched — the
 * calculator stays pure), the demand estimate comes from the time-series model:
 * velocity becomes the model's expected daily demand, the suggested order
 * covers the forecast demand plus a safety buffer sized from the p90 band, and
 * the forecast-only insights (projected stockout date, stockout risk, demand
 * trend, projected revenue, dead-stock detection) light up. Without a snapshot
 * the behaviour is exactly the historical window-average formula.
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
     * @param  ForecastSnapshot|null  $forecast  model forecast; null falls back to the window average
     * @param  float  $unitPrice  selling price per unit (for projected revenue; forecast mode only)
     */
    public function analyze(
        int $currentStock,
        int $unitsOutInWindow,
        int $leadTimeDays,
        float $unitCost,
        DateTimeImmutable $today,
        ?ReorderConfig $config = null,
        ?ForecastSnapshot $forecast = null,
        float $unitPrice = 0.0,
    ): RecommendationMetrics {
        $config ??= ReorderConfig::defaults();
        $usingForecast = $forecast !== null;

        // 1. sales velocity (avg units/day): model expectation, or the window average
        $salesVelocity = $usingForecast
            ? $forecast->expectedDailyDemand
            : $unitsOutInWindow / $config->velocityWindowDays;

        // 2. days of stock left — null when there is no (expected) demand
        $daysOfStockLeft = $salesVelocity > 1e-9
            ? $currentStock / $salesVelocity
            : null;

        // 3. needs reorder?
        $needsReorder = $daysOfStockLeft !== null
            && $daysOfStockLeft < ($leadTimeDays + $config->safetyBufferDays);

        // 4. suggested reorder quantity (cover lead time + coverage period).
        //    Forecast mode sums the actual daily curve and adds a safety buffer
        //    from the p90 band; fallback projects the flat average.
        if ($usingForecast) {
            $safetyUnits = $forecast->p90DemandOverLeadTime !== null
                ? max(0.0, $forecast->p90DemandOverLeadTime - $forecast->demandOverLeadTime)
                : $salesVelocity * $config->safetyBufferDays;
            $suggestedReorderQty = (int) ceil($forecast->demandLeadPlusCoverage + $safetyUnits);
        } else {
            $suggestedReorderQty = (int) ceil($salesVelocity * ($leadTimeDays + $config->coveragePeriodDays));
        }

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

        // 8. dead stock (forecast mode only): the model expects demand to have
        //    effectively stopped while units are still on the shelf.
        $isDeadStock = $usingForecast
            && $salesVelocity < $config->deadStockDailyDemand
            && $currentStock > 0;

        $type = match (true) {
            $needsReorder => 'reorder',
            $isDeadStock => 'dead_stock',
            $isOverstocked => 'overstock',
            default => 'healthy',
        };

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
            reasoning: $this->reasoning($type, $salesVelocity, $daysOfStockLeft, $leadTimeDays, $suggestedReorderQty, $cashTiedUp, $config, $forecast),
            currentStock: $currentStock,
            leadTimeDays: $leadTimeDays,
            unitCost: $unitCost,
            forecastSource: $usingForecast ? 'model' : 'fallback',
            modelUsed: $forecast?->modelUsed,
            forecastGeneratedAt: $forecast?->generatedAt->format('c'),
            projectedStockoutDate: $usingForecast ? $this->projectedStockoutDate($currentStock, $forecast, $today) : null,
            stockoutRisk: $usingForecast && ! $isDeadStock ? $this->stockoutRisk($currentStock, $forecast, $config) : null,
            demandTrendPct: $usingForecast ? $this->demandTrendPct($forecast) : null,
            projectedUnits30d: $usingForecast ? $forecast->expectedDailyDemand * 30 : null,
            projectedRevenue30d: $usingForecast ? $forecast->expectedDailyDemand * 30 * $unitPrice : null,
        );
    }

    /**
     * Walk stock down the daily forecast curve: the first day cumulative
     * expected demand reaches the on-hand quantity. Null when stock outlasts
     * the forecast horizon.
     */
    private function projectedStockoutDate(int $currentStock, ForecastSnapshot $forecast, DateTimeImmutable $today): ?string
    {
        $cumulative = 0.0;
        foreach ($forecast->dailyMeans as $day => $mean) {
            $cumulative += $mean;
            if ($cumulative >= $currentStock) {
                return $today->modify("+{$day} days")->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * - high: expected demand during the lead time already exhausts stock —
     *   even ordering today likely means a stockout;
     * - medium: the p90 (worst-case) band exhausts stock, expected demand doesn't;
     * - low: covered even in the worst case.
     *
     * @return 'high'|'medium'|'low'
     */
    private function stockoutRisk(int $currentStock, ForecastSnapshot $forecast, ReorderConfig $config): string
    {
        if ($currentStock < $forecast->demandOverLeadTime) {
            return 'high';
        }

        $worstCase = $forecast->p90DemandOverLeadTime
            ?? $forecast->demandOverLeadTime + $forecast->expectedDailyDemand * $config->safetyBufferDays;

        return $currentStock < $worstCase ? 'medium' : 'low';
    }

    /**
     * Expected demand for the next 28 days vs the actual units sold in the
     * previous 28 — the "rising/declining" signal. Null when there is too
     * little recent history to compare against.
     */
    private function demandTrendPct(ForecastSnapshot $forecast): ?float
    {
        if ($forecast->actualsLast28d < 1.0) {
            return null;
        }

        return ($forecast->expectedDailyDemand * 28 - $forecast->actualsLast28d) / $forecast->actualsLast28d * 100.0;
    }

    /**
     * Plain-language explanation. Rounding lives here (display only).
     *
     * @param  'reorder'|'dead_stock'|'overstock'|'healthy'  $type
     */
    private function reasoning(
        string $type,
        float $salesVelocity,
        ?float $daysOfStockLeft,
        int $leadTimeDays,
        int $suggestedReorderQty,
        float $cashTiedUp,
        ReorderConfig $config,
        ?ForecastSnapshot $forecast,
    ): string {
        if ($forecast === null) {
            return $this->fallbackReasoning($type, $salesVelocity, $daysOfStockLeft, $leadTimeDays, $suggestedReorderQty, $cashTiedUp, $config);
        }

        $model = $forecast->modelUsed;
        $perWeek = (int) round($salesVelocity * 7);
        $days = $daysOfStockLeft === null ? 0 : (int) round($daysOfStockLeft);

        return match ($type) {
            'reorder' => sprintf(
                'Forecast (%s): ~%d/week expected with ~%d days left. With a %d-day lead time, order %d units now to avoid a stockout.',
                $model,
                $perWeek,
                $days,
                $leadTimeDays,
                $suggestedReorderQty,
            ),
            'dead_stock' => sprintf(
                'Forecast (%s): demand has effectively stopped — about $%s could be freed by clearing the remaining stock.',
                $model,
                number_format($cashTiedUp, 2),
            ),
            'overstock' => sprintf(
                'Forecast (%s): ~%d days of cover expected. About $%s is tied up in excess inventory — consider a promotion.',
                $model,
                $days,
                number_format($cashTiedUp, 2),
            ),
            default => sprintf(
                'Forecast (%s): healthy — about %d days of cover at ~%d/week expected. No action needed.',
                $model,
                $days,
                $perWeek,
            ),
        };
    }

    /**
     * The original window-average wording — kept byte-identical so the
     * fallback path (no forecast) reads exactly as before.
     *
     * @param  'reorder'|'dead_stock'|'overstock'|'healthy'  $type
     */
    private function fallbackReasoning(
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
