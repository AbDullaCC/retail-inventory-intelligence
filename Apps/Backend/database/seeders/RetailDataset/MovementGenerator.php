<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

use DateTimeImmutable;

/**
 * Turns a SKU's real daily sales into a full stock_movements ledger by
 * simulating the retailer's replenishment side, which the dataset lacks:
 * opening stock, (s, S) purchase orders arriving after the standard lead time,
 * and an expedited-restock guard so recorded demand is never truncated and the
 * ledger never goes negative.
 *
 * The sales ('out') rows are the real data; only the 'in' side is synthetic.
 * After simulating, the closing stock is nudged to a per-SKU "persona" target
 * (days of cover) by resizing synthetic 'in' rows — never sales — so the demo
 * catalogue reliably shows reorder, overstock and healthy verdicts.
 *
 * Pure and framework-free: dates in, movement-row arrays out.
 */
final class MovementGenerator
{
    /** Mirrors ReorderConfig::DEFAULT_LEAD_TIME_DAYS. */
    private const LEAD_TIME_DAYS = 7;

    /** Reorder point s: days of average demand (lead time + safety, per ReorderConfig). */
    private const REORDER_POINT_DAYS = 10;

    /** Order-up-to S adds this many days of cover on top of s. */
    private const ORDER_UP_TO_EXTRA_DAYS = 14;

    /** Understocked personas stop ordering this close to the end. */
    private const FINAL_SUPPRESSION_DAYS = 21;

    private const TIME_RECEIVE = '08:00:00';

    private const TIME_RETURNS = '12:00:00';

    private const TIME_EXPEDITE = '16:00:00';

    private const TIME_SALE = '17:00:00';

    /**
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days  dataset-time daily aggregates
     * @param  float|null  $personaTargetDays  desired closing days-of-cover (null: leave natural)
     * @return array{rows: list<array{type: string, quantity: int, quantity_before: int, quantity_after: int, reason: string, created_at: string}>, closing: int, opening_at: string}
     */
    public function generate(array $days, string $maxDay, int $shiftDays, ?float $personaTargetDays): array
    {
        ksort($days);

        $firstSaleDay = $this->firstSaleDay($days);
        if ($firstSaleDay === null) {
            return ['rows' => [], 'closing' => 0, 'opening_at' => $this->shift($maxDay, $shiftDays)];
        }

        [$s, $S] = $this->policyLevels($days, $firstSaleDay);
        $suppressLatePos = $personaTargetDays !== null && $personaTargetDays < self::REORDER_POINT_DAYS;
        $suppressionCutoff = $this->addDays($maxDay, -self::FINAL_SUPPRESSION_DAYS);
        $lastPlacementDay = $this->addDays($maxDay, -self::LEAD_TIME_DAYS);

        /** @var list<array{type: string, quantity: int, quantity_before: int, quantity_after: int, reason: string, created_at: string}> $rows */
        $rows = [];
        $onHand = 0;
        /** @var array<string, int> $pipeline arrival day => quantity */
        $pipeline = [];

        $emit = function (string $type, int $qty, string $day, string $time, string $reason) use (&$rows, &$onHand): void {
            $before = $onHand;
            $onHand = $type === 'out' ? $onHand - $qty : $onHand + $qty;
            $rows[] = [
                'type' => $type,
                'quantity' => $qty,
                'quantity_before' => $before,
                'quantity_after' => $onHand,
                'reason' => $reason,
                'created_at' => $day.' '.$time,
            ];
        };

        $openingDay = $this->addDays($firstSaleDay, -1);
        $emit('in', $S, $openingDay, self::TIME_RECEIVE, 'Opening stock (imported)');

        for ($day = $firstSaleDay; $day <= $maxDay; $day = $this->addDays($day, 1)) {
            if (isset($pipeline[$day])) {
                $emit('in', $pipeline[$day], $day, self::TIME_RECEIVE, 'Purchase order received (imported)');
                unset($pipeline[$day]);
            }

            $today = $days[$day] ?? null;

            $returns = $today['returned'] ?? 0;
            if ($returns > 0) {
                $emit('in', $returns, $day, self::TIME_RETURNS, 'Customer returns (imported)');
            }

            $demand = $today['sold'] ?? 0;
            if ($demand > 0) {
                if ($demand > $onHand) {
                    $emit('in', max($S - $onHand, $demand - $onHand), $day, self::TIME_EXPEDITE, 'Expedited restock (imported)');
                }
                $orders = max(1, $today['orders'] ?? 1);
                $emit('out', $demand, $day, self::TIME_SALE, sprintf('Daily sales — %d order%s (imported)', $orders, $orders === 1 ? '' : 's'));
            }

            $suppressed = $suppressLatePos && $day > $suppressionCutoff;
            if ($onHand < $s && $pipeline === [] && $day <= $lastPlacementDay && ! $suppressed) {
                $pipeline[$this->addDays($day, self::LEAD_TIME_DAYS)] = max(1, $S - $onHand);
            }
        }

        $this->applyPersonaTarget($rows, $onHand, $days, $maxDay, $personaTargetDays);

        foreach ($rows as &$row) {
            [$d, $t] = explode(' ', $row['created_at']);
            $row['created_at'] = $this->shift($d, $shiftDays).' '.$t;
        }
        unset($row);

        return ['rows' => $rows, 'closing' => $onHand, 'opening_at' => $this->shift($openingDay, $shiftDays).' '.self::TIME_RECEIVE];
    }

    /**
     * Nudge closing stock to ≈ target days of cover (at the trailing 14-day
     * velocity — the same window Intelligence uses) by resizing synthetic 'in'
     * rows. Sales rows are never touched.
     *
     * @param  list<array{type: string, quantity: int, quantity_before: int, quantity_after: int, reason: string, created_at: string}>  $rows
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     */
    private function applyPersonaTarget(array &$rows, int &$onHand, array $days, string $maxDay, ?float $targetDays): void
    {
        if ($targetDays === null || $rows === []) {
            return;
        }

        $velocity = $this->trailingVelocity($days, $maxDay);
        if ($velocity <= 0.0) {
            return; // dormant SKU — stays a "no recent sales" demo case
        }

        $delta = $onHand - max(0, (int) round($velocity * $targetDays));

        if ($delta > 0) {
            $this->shrinkSyntheticInflows($rows, $delta);
        } elseif ($delta < 0) {
            $this->growLastInflow($rows, -$delta);
        }

        $onHand = $rows[array_key_last($rows)]['quantity_after'];
    }

    /**
     * @param  list<array{type: string, quantity: int, quantity_before: int, quantity_after: int, reason: string, created_at: string}>  $rows
     */
    private function shrinkSyntheticInflows(array &$rows, int $delta): void
    {
        for ($k = count($rows) - 1; $k >= 0 && $delta > 0; $k--) {
            if ($rows[$k]['type'] !== 'in' || $rows[$k]['reason'] === 'Customer returns (imported)') {
                continue;
            }

            // Never push any later balance below zero.
            $minAfter = PHP_INT_MAX;
            for ($j = $k, $n = count($rows); $j < $n; $j++) {
                $minAfter = min($minAfter, $rows[$j]['quantity_after']);
            }

            $reduce = min($delta, $rows[$k]['quantity'], $minAfter);
            if ($reduce <= 0) {
                continue;
            }

            $rows[$k]['quantity'] -= $reduce;
            for ($j = $k, $n = count($rows); $j < $n; $j++) {
                if ($j > $k) {
                    $rows[$j]['quantity_before'] -= $reduce;
                }
                $rows[$j]['quantity_after'] -= $reduce;
            }
            $delta -= $reduce;

            if ($rows[$k]['quantity'] === 0) {
                array_splice($rows, $k, 1);
            }
        }
    }

    /**
     * @param  list<array{type: string, quantity: int, quantity_before: int, quantity_after: int, reason: string, created_at: string}>  $rows
     */
    private function growLastInflow(array &$rows, int $grow): void
    {
        for ($k = count($rows) - 1; $k >= 0; $k--) {
            if ($rows[$k]['type'] !== 'in' || $rows[$k]['reason'] === 'Customer returns (imported)') {
                continue;
            }

            $rows[$k]['quantity'] += $grow;
            for ($j = $k, $n = count($rows); $j < $n; $j++) {
                if ($j > $k) {
                    $rows[$j]['quantity_before'] += $grow;
                }
                $rows[$j]['quantity_after'] += $grow;
            }

            return;
        }
    }

    /**
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     */
    private function trailingVelocity(array $days, string $maxDay): float
    {
        $cutoff = $this->addDays($maxDay, -14);

        $units = 0;
        foreach ($days as $day => $tot) {
            if ($day > $cutoff) {
                $units += $tot['sold'];
            }
        }

        return $units / 14;
    }

    /**
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     * @return array{0: int, 1: int} [reorder point s, order-up-to level S]
     */
    private function policyLevels(array $days, string $firstSaleDay): array
    {
        $lastSaleDay = $firstSaleDay;
        $units = 0;
        foreach ($days as $day => $tot) {
            if ($tot['sold'] > 0) {
                $lastSaleDay = max($lastSaleDay, $day);
            }
            $units += $tot['sold'] - $tot['returned'];
        }

        $activeSpan = max(1, $this->diffDays($firstSaleDay, $lastSaleDay) + 1);
        $avgDaily = max(0.05, $units / $activeSpan);

        $s = max(1, (int) ceil($avgDaily * self::REORDER_POINT_DAYS));

        return [$s, $s + max(1, (int) ceil($avgDaily * self::ORDER_UP_TO_EXTRA_DAYS))];
    }

    /**
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     */
    private function firstSaleDay(array $days): ?string
    {
        foreach ($days as $day => $tot) {
            if ($tot['sold'] > 0) {
                return $day;
            }
        }

        return null;
    }

    private function addDays(string $day, int $offset): string
    {
        return (new DateTimeImmutable($day))->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    }

    private function diffDays(string $from, string $to): int
    {
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%a');
    }

    private function shift(string $day, int $shiftDays): string
    {
        return $this->addDays($day, $shiftDays);
    }
}
