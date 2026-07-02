<?php

declare(strict_types=1);

use App\Modules\Forecast\Controllers\ForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('forecast/summary', [ForecastController::class, 'summary']);
    Route::get('products/{product}/forecast', [ForecastController::class, 'show'])->whereNumber('product');
});
