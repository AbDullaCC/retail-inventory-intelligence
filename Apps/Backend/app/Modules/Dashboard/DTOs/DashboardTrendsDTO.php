<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Time-series feed for the dashboard charts: zero-filled per-day movement
 * totals plus current stock value by category. `series` is always a full
 * calendar (the frontend never gap-fills).
 */
final class DashboardTrendsDTO extends BaseData
{
    /**
     * @param  list<array{date: string, units_in: int, units_out: int, movements: int}>  $series
     * @param  list<array{category_id: int, category_name: string, stock_value: float, units: int}>  $categoryValues
     */
    public function __construct(
        public readonly int $days,
        public readonly array $series,
        public readonly array $categoryValues,
    ) {}

    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'series' => $this->series,
            'category_values' => $this->categoryValues,
        ];
    }
}
