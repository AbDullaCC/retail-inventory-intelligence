<?php

declare(strict_types=1);

use App\Modules\Intelligence\Controllers\IntelligenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('intelligence/recommendations', [IntelligenceController::class, 'index']);
    Route::get('products/{product}/recommendation', [IntelligenceController::class, 'show'])->whereNumber('product');
});
