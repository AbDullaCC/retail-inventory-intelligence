<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Shopify\Console\ShopifySyncCommand;
use App\Modules\Shopify\Services\Contracts\ShopifySyncServiceInterface;
use App\Modules\Shopify\Services\ShopifySyncService;

final class ShopifyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShopifySyncServiceInterface::class, ShopifySyncService::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ShopifySyncCommand::class]);
        }
    }
}
