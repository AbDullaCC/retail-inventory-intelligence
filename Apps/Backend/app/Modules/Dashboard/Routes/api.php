<?php

declare(strict_types=1);

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/trends', [DashboardController::class, 'trends']);
});
