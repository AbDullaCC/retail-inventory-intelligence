<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Providers;

use App\Modules\Forecast\Console\ForecastEvaluateCommand;
use App\Modules\Forecast\Console\ForecastRunCommand;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Forecast\Services\Contracts\ForecastRunnerInterface;
use App\Modules\Forecast\Services\ForecastReader;
use App\Modules\Forecast\Services\ForecastRunner;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class ForecastServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ForecastRunnerInterface::class, ForecastRunner::class);
        $this->app->bind(ForecastReaderInterface::class, ForecastReader::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ForecastRunCommand::class, ForecastEvaluateCommand::class]);
        }
    }
}
