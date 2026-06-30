<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Providers;

use App\Modules\Intelligence\Console\InventoryInsightsCommand;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Intelligence\Services\IntelligenceService;
use App\Modules\Intelligence\Support\ReorderConfig;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class IntelligenceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IntelligenceServiceInterface::class, IntelligenceService::class);

        // Bind the tunable config so it can be swapped/overridden centrally.
        $this->app->bind(ReorderConfig::class, static fn (): ReorderConfig => ReorderConfig::defaults());
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([InventoryInsightsCommand::class]);
        }
    }
}
