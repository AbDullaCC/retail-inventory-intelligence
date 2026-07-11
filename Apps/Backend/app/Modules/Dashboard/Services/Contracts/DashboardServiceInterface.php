<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services\Contracts;

use App\Modules\Dashboard\DTOs\DashboardSummaryDTO;
use App\Modules\Dashboard\DTOs\DashboardTrendsDTO;

interface DashboardServiceInterface
{
    public function summary(): DashboardSummaryDTO;

    /**
     * Daily in/out movement totals over the trailing window (zero-filled),
     * plus stock value by category. `$productId` narrows the series to one
     * product (and empties the category breakdown).
     */
    public function trends(int $days, ?int $productId = null): DashboardTrendsDTO;

    /**
     * Best sellers: products ranked by units sold (`out` movements) over the
     * trailing window. Revenue is estimated at the current product price.
     *
     * @return list<array{product_id: int, sku: string, name: string, units_sold: int, revenue: float}>
     */
    public function topProducts(int $days, int $limit): array;
}
