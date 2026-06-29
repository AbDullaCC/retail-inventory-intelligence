<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceInterface $service,
    ) {
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::item($this->service->summary());
    }
}
