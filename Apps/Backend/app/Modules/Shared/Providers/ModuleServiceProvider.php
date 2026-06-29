<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base class for every module's service provider.
 *
 * Gives each module a one-liner for registering its own REST routes under the
 * shared `/api` prefix and `api` middleware group, so modules stay self-contained.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    protected function loadApiRoutes(string $path): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group($path);
    }
}
