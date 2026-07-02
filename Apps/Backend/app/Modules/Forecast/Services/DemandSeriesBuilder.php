<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services;

use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use DateTimeImmutable;

/**
 * Builds zero-filled daily demand series from the stock_movements ledger —
 * the single place that turns ledger rows into the calendar shape the
 * forecasting sidecar (and the chart endpoint) consume. Only `out` movements
 * count as demand; a day without sales is a real zero, not a gap.
 */
final class DemandSeriesBuilder
{
    /**
     * Per-product daily series from each product's first sale (capped at
     * $historyDays back) through yesterday.
     *
     * @param  list<int>|null  $productIds  null = every product with sales
     * @return array<int, list<array{date: string, qty: int}>>
     */
    public function seriesByProduct(DateTimeImmutable $today, int $historyDays, ?array $productIds = null): array
    {
        $daily = $this->dailyTotals($today->modify("-{$historyDays} days"), $productIds);
        $yesterday = $today->modify('-1 day')->format('Y-m-d');

        $series = [];
        foreach ($daily as $productId => $days) {
            $series[$productId] = $this->zeroFill($days, (string) array_key_first($days), $yesterday);
        }

        return $series;
    }

    /**
     * Fixed zero-filled window (e.g. the last 90 days) for one product —
     * chart-friendly: always exactly $days points ending yesterday.
     *
     * @return list<array{date: string, qty: int}>
     */
    public function actualsFor(int $productId, int $days, DateTimeImmutable $today): array
    {
        $start = $today->modify(sprintf('-%d days', $days))->format('Y-m-d');
        $daily = $this->dailyTotals($today->modify(sprintf('-%d days', $days)), [$productId]);

        return $this->zeroFill($daily[$productId] ?? [], $start, $today->modify('-1 day')->format('Y-m-d'));
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array<int, array<string, int>> product id => [day => units out]
     */
    private function dailyTotals(DateTimeImmutable $start, ?array $productIds): array
    {
        $rows = StockMovement::query()
            ->where('type', StockMovementType::Out->value)
            ->where('created_at', '>=', $start->format('Y-m-d 00:00:00'))
            ->when($productIds !== null, static fn ($query) => $query->whereIn('product_id', $productIds ?? []))
            ->groupBy('product_id')
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('product_id, DATE(created_at) as day, SUM(quantity) as qty')
            ->orderBy('day')
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[(int) $row->product_id][(string) $row->day] = (int) $row->qty;
        }

        return $daily;
    }

    /**
     * @param  array<string, int>  $days
     * @return list<array{date: string, qty: int}>
     */
    private function zeroFill(array $days, string $from, string $to): array
    {
        $series = [];
        for ($day = new DateTimeImmutable($from), $end = new DateTimeImmutable($to); $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = ['date' => $key, 'qty' => $days[$key] ?? 0];
        }

        return $series;
    }
}
