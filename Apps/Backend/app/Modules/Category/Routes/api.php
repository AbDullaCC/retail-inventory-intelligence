<?php

declare(strict_types=1);

use App\Modules\Category\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{category}', [CategoryController::class, 'show'])->whereNumber('category');
    Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->whereNumber('category');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category');
});
