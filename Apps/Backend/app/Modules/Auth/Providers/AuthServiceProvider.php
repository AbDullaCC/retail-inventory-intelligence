<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class AuthServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
