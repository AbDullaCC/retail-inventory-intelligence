<?php

declare(strict_types=1);

use App\Modules\Shopify\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('shopify/status', [ShopifyController::class, 'status']);
    Route::post('shopify/connect', [ShopifyController::class, 'connect']);
    Route::post('shopify/sync', [ShopifyController::class, 'sync']);
    Route::delete('shopify/connection', [ShopifyController::class, 'disconnect']);
});
