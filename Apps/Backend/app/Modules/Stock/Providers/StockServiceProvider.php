<?php

declare(strict_types=1);

namespace App\Modules\Stock\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use App\Modules\Stock\Services\StockService;

final class StockServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockServiceInterface::class, StockService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
