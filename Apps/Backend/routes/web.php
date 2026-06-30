<?php

use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API documentation (Swagger UI + OpenAPI spec)
Route::get('/docs', [DocsController::class, 'ui']);
Route::get('/docs/openapi.json', [DocsController::class, 'spec']);
