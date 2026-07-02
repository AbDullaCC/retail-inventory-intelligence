<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Derives product attributes for a curated SKU from its raw transaction
 * aggregates. The dataset carries no cost or category data, so those are
 * synthesized (cost as a fixed margin on the median selling price) — a fact
 * documented in the README's "Demo data" section.
 */
final class ProductSynthesizer
{
    /** The dataset has no cost data; assume a 35% gross margin. */
    private const COST_RATIO = 0.65;

    private const REORDER_COVER_DAYS = 7;

    public function __construct(private readonly CategoryKeywordMap $categories) {}

    /**
     * @param  array{days: array<string, array{sold: int, returned: int, orders: int}>, prices: array<string, int>, descriptions: array<string, int>, first: string, last: string, units: int}  $agg
     * @return array{sku: string, category: string, name: string, price: float, cost: float, reorder_level: int}
     */
    public function synthesize(string $sku, array $agg, string $maxDay): array
    {
        $description = $this->canonicalDescription($agg['descriptions']) ?? sprintf('Item %s', $sku);
        $price = $this->medianPrice($agg['prices']);

        return [
            'sku' => $sku,
            'category' => $this->categories->categorize($description),
            'name' => Str::title(mb_strtolower(preg_replace('/\s+/', ' ', $description) ?? $description)),
            'price' => $price,
            'cost' => round($price * self::COST_RATIO, 2),
            'reorder_level' => max(5, (int) ceil($this->recentDailyUnits($agg['days'], $maxDay) * self::REORDER_COVER_DAYS)),
        ];
    }

    /**
     * @param  array<string, int>  $descriptions  description => occurrence count
     */
    private function canonicalDescription(array $descriptions): ?string
    {
        unset($descriptions['']);
        if ($descriptions === []) {
            return null;
        }

        arsort($descriptions);

        return (string) array_key_first($descriptions);
    }

    /**
     * Weighted median over the price histogram — robust against the dataset's
     * occasional manual-entry outliers.
     *
     * @param  array<string, int>  $histogram  price => occurrence count
     */
    private function medianPrice(array $histogram): float
    {
        if ($histogram === []) {
            return 0.0;
        }

        uksort($histogram, static fn (string $a, string $b): int => (float) $a <=> (float) $b);

        $total = array_sum($histogram);
        $midpoint = ($total + 1) / 2;
        $running = 0;
        foreach ($histogram as $price => $count) {
            $running += $count;
            if ($running >= $midpoint) {
                return round((float) $price, 2);
            }
        }

        return round((float) array_key_last($histogram), 2);
    }

    /**
     * Average daily units over the final 90 dataset days.
     *
     * @param  array<string, array{sold: int, returned: int, orders: int}>  $days
     */
    private function recentDailyUnits(array $days, string $maxDay): float
    {
        $cutoff = (new DateTimeImmutable($maxDay))->modify('-90 days')->format('Y-m-d');

        $units = 0;
        foreach ($days as $day => $tot) {
            if ($day > $cutoff) {
                $units += $tot['sold'] - $tot['returned'];
            }
        }

        return max(0, $units) / 90;
    }
}
