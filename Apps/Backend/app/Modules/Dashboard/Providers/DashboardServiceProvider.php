<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Providers;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class DashboardServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
