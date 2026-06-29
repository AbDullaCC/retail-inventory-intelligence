<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services\Contracts;

use App\Modules\Dashboard\DTOs\DashboardSummaryDTO;

interface DashboardServiceInterface
{
    public function summary(): DashboardSummaryDTO;
}
