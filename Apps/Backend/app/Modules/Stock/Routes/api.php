<?php

declare(strict_types=1);

use App\Modules\Stock\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('stock-movements', [StockController::class, 'index']);
    Route::get('products/{product}/stock-movements', [StockController::class, 'history'])->whereNumber('product');
    Route::post('products/{product}/stock-adjustments', [StockController::class, 'adjust'])->whereNumber('product');
});
