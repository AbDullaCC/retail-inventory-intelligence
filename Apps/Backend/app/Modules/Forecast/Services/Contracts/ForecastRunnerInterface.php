<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services\Contracts;

use App\Modules\Forecast\Exceptions\ForecastServiceUnavailableException;

interface ForecastRunnerInterface
{
    /**
     * Build demand series, obtain forecasts from the sidecar and replace each
     * product's stored forecast row.
     *
     * @param  list<int>|null  $productIds  null = every product with sales history
     * @return array{forecasted: int, skipped: int, models: array<string, int>}
     *
     * @throws ForecastServiceUnavailableException
     */
    public function run(?array $productIds = null): array;
}
