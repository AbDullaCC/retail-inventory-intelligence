<?php

declare(strict_types=1);

use App\Modules\Product\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');
    Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->whereNumber('product');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product');
});
