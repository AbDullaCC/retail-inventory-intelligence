<?php

declare(strict_types=1);

namespace App\Modules\Forecast\DTOs;

use DateTimeImmutable;

/**
 * A product's model forecast in the shape the pure ReorderCalculator consumes.
 * Framework-free by design: the calculator receives one of these (or null) —
 * it never fetches anything itself.
 */
final class ForecastSnapshot
{
    /**
     * @param  list<float>  $dailyMeans  point forecast per horizon day, day 1 = today
     * @param  float  $actualsLast28d  units actually sold in the 28 days before the run
     */
    public function __construct(
        public readonly float $expectedDailyDemand,
        public readonly float $demandOverLeadTime,
        public readonly ?float $p90DemandOverLeadTime,
        public readonly float $demandLeadPlusCoverage,
        public readonly array $dailyMeans,
        public readonly float $actualsLast28d,
        public readonly string $modelUsed,
        public readonly DateTimeImmutable $generatedAt,
        public readonly int $horizonDays,
        public readonly int $leadTimeDays,
    ) {}
}
