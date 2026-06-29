<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Each feature module registers its own routes from its service provider
| (see app/Modules/<Module>/Routes/api.php). Only cross-cutting endpoints
| live here.
|
*/

Route::get('/ping', fn () => response()->json([
    'message' => 'pong',
    'app' => config('app.name'),
]));
