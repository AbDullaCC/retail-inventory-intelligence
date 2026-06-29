<?php

declare(strict_types=1);

namespace App\Modules\Category\Providers;

use App\Modules\Category\Services\CategoryService;
use App\Modules\Category\Services\Contracts\CategoryServiceInterface;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class CategoryServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
