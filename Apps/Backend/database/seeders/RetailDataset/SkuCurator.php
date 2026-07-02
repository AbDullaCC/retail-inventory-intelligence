<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

use DateTimeImmutable;

/**
 * Selects the demo catalogue from the full dataset: the densest recent sellers
 * (so the velocity window and forecasts have plenty of signal) topped up with
 * deliberately diverse demand patterns — seasonal, intermittent and declining —
 * so the forecasting model-selection logic has something to route on.
 *
 * Selection is fully deterministic: no randomness, ordering ties broken by SKU.
 */
final class SkuCurator
{
    private const CORE_PICKS = 200;

    private const PATTERN_PICKS = 20;

    private const PATTERN_POOL = 1000;

    private const RECENT_WINDOW_DAYS = 180;

    /**
     * @param  array<string, array{days: array<string, array{sold: int, returned: int, orders: int}>, units: int, first: string, last: string}>  $aggregates
     * @return list<string> selected SKUs, densest-recent first (index drives the demo persona)
     */
    public function select(array $aggregates, string $maxDay, int $limit): array
    {
        $recentCutoff = (new DateTimeImmutable($maxDay))
            ->modify(sprintf('-%d days', self::RECENT_WINDOW_DAYS))
            ->format('Y-m-d');

        $stats = [];
        foreach ($aggregates as $sku => $agg) {
            if ($agg['units'] <= 0) {
                continue;
            }

            $saleDays = 0;
            $recentSaleDays = 0;
            foreach ($agg['days'] as $day => $tot) {
                if ($tot['sold'] > 0) {
                    $saleDays++;
                    if ($day > $recentCutoff) {
                        $recentSaleDays++;
                    }
                }
            }

            $stats[$sku] = [
                'saleDays' => $saleDays,
                'recentSaleDays' => $recentSaleDays,
                'weekly' => $this->weeklyUnits($agg['days'], $agg['first']),
                'spanDays' => $this->daysBetween($agg['first'], $agg['last']) + 1,
                'units' => $agg['units'],
            ];
        }

        // Densest recent sellers first; SKU as the deterministic tie-break.
        uksort($stats, static function (string $a, string $b) use ($stats): int {
            return [$stats[$b]['recentSaleDays'], $stats[$b]['saleDays'], $a]
                <=> [$stats[$a]['recentSaleDays'], $stats[$a]['saleDays'], $b];
        });

        $selected = array_slice(array_keys($stats), 0, min(self::CORE_PICKS, $limit));
        $picked = array_flip($selected);

        $pool = array_slice(array_keys($stats), 0, self::PATTERN_POOL);
        foreach (['seasonal', 'intermittent', 'declining'] as $pattern) {
            $added = 0;
            foreach ($pool as $sku) {
                if (count($selected) >= $limit || $added >= self::PATTERN_PICKS) {
                    break;
                }
                if (isset($picked[$sku]) || ! $this->matches($pattern, $stats[$sku])) {
                    continue;
                }
                $selected[] = $sku;
                $picked[$sku] = true;
                $added++;
            }
        }

        // PHP silently casts numeric-string array keys ("22197") to ints;
        // restore the SKUs to strings on the way out.
        return array_map('strval', array_slice($selected, 0, $limit));
    }

    /**
     * @param  array{saleDays: int, weekly: list<int>, spanDays: int, units: int}  $stat
     */
    private function matches(string $pattern, array $stat): bool
    {
        return match ($pattern) {
            // >= 50% of lifetime units inside the best 12-week span.
            'seasonal' => $stat['units'] >= 100
                && $this->bestSpanShare($stat['weekly'], 12) >= 0.5,
            // Sells on < 25% of its active days, but often enough to matter.
            'intermittent' => $stat['saleDays'] >= 20
                && $stat['spanDays'] > 0
                && ($stat['saleDays'] / $stat['spanDays']) < 0.25,
            // Final quarter runs at < 40% of the first quarter's volume.
            'declining' => $stat['units'] >= 200
                && count($stat['weekly']) >= 26
                && $this->quarterUnits($stat['weekly'], true) > 0
                && $this->quarterUnits($stat['weekly'], false) < 0.4 * $this->quarterUnits($stat['weekly'], true),
            default => false,
        };
    }

    /**
     * Net units per week since the SKU's first sale (index 0 = first week).
     *
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     * @return list<int>
     */
    private function weeklyUnits(array $days, string $first): array
    {
        $weekly = [];
        foreach ($days as $day => $tot) {
            $week = intdiv($this->daysBetween($first, $day), 7);
            $weekly[$week] = ($weekly[$week] ?? 0) + $tot['sold'] - $tot['returned'];
        }
        ksort($weekly);

        // Re-index into a dense list, filling silent weeks with zero.
        $dense = array_fill(0, ($weekly === [] ? 0 : array_key_last($weekly) + 1), 0);
        foreach ($weekly as $week => $units) {
            $dense[$week] = $units;
        }

        return $dense;
    }

    /**
     * Largest share of total units captured by any contiguous span of weeks.
     *
     * @param  list<int>  $weekly
     */
    private function bestSpanShare(array $weekly, int $spanWeeks): float
    {
        $total = array_sum($weekly);
        if ($total <= 0 || count($weekly) <= $spanWeeks) {
            return 0.0;
        }

        $window = array_sum(array_slice($weekly, 0, $spanWeeks));
        $best = $window;
        for ($i = $spanWeeks, $n = count($weekly); $i < $n; $i++) {
            $window += $weekly[$i] - $weekly[$i - $spanWeeks];
            $best = max($best, $window);
        }

        return $best / $total;
    }

    /**
     * @param  list<int>  $weekly
     */
    private function quarterUnits(array $weekly, bool $first): int
    {
        $slice = $first
            ? array_slice($weekly, 0, 13)
            : array_slice($weekly, -13);

        return max(0, array_sum($slice));
    }

    private function daysBetween(string $from, string $to): int
    {
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%a');
    }
}
