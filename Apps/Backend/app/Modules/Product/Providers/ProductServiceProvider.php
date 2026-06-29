<?php

declare(strict_types=1);

namespace App\Modules\Product\Providers;

use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use App\Modules\Product\Services\ProductService;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class ProductServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
