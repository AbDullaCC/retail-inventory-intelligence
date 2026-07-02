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
}
