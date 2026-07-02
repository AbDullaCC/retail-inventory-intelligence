<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Support;

use App\Modules\Intelligence\Services\ReorderCalculator;

/**
 * Named, configurable constants that drive the reorder/overstock intelligence.
 *
 * Defaults live as class constants so they are documented in one place; an
 * instance can override any of them, which keeps {@see ReorderCalculator}
 * a pure, deterministic function of (inputs, config).
 */
final class ReorderConfig
{
    /** Trailing window (days) over which sales velocity is averaged. */
    public const VELOCITY_WINDOW_DAYS = 14;

    /** Extra days of cover required on top of the lead time before reordering. */
    public const SAFETY_BUFFER_DAYS = 3;

    /** Days of demand a reorder should cover (beyond the lead time). */
    public const COVERAGE_PERIOD_DAYS = 14;

    /** Above this many days of stock-on-hand a product is considered overstocked. */
    public const OVERSTOCK_THRESHOLD_DAYS = 60;

    /** Days of stock treated as "needed"; anything beyond ties up cash. */
    public const NEEDED_STOCK_DAYS = 30;

    /** Fallback supplier lead time — the product model has no lead-time field. */
    public const DEFAULT_LEAD_TIME_DAYS = 7;

    /** Fallback unit cost when a product's `cost` is null. */
    public const DEFAULT_UNIT_COST = 0.0;

    /**
     * Below this model-expected daily demand a product with stock on hand is
     * "dead stock" (≈ under 1.5 units/month). Forecast mode only.
     */
    public const DEAD_STOCK_DAILY_DEMAND = 0.05;

    public function __construct(
        public readonly int $velocityWindowDays = self::VELOCITY_WINDOW_DAYS,
        public readonly int $safetyBufferDays = self::SAFETY_BUFFER_DAYS,
        public readonly int $coveragePeriodDays = self::COVERAGE_PERIOD_DAYS,
        public readonly int $overstockThresholdDays = self::OVERSTOCK_THRESHOLD_DAYS,
        public readonly int $neededStockDays = self::NEEDED_STOCK_DAYS,
        public readonly int $defaultLeadTimeDays = self::DEFAULT_LEAD_TIME_DAYS,
        public readonly float $defaultUnitCost = self::DEFAULT_UNIT_COST,
        public readonly float $deadStockDailyDemand = self::DEAD_STOCK_DAILY_DEMAND,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }
}
