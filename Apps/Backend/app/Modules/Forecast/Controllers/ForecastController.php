<?php

namespace App\Modules\Forecast\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Forecast\Services\Contracts\ForecastRunnerInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class ForecastController extends Controller
{
    public function __construct(
        private readonly ForecastReaderInterface $reader,
    ) {}

    public function run(ForecastRunnerInterface $runner): JsonResponse
    {
        // 250 products take ~1-3 minutes against the sidecar.
        set_time_limit(0);

        $summary = $runner->run();

        return ApiResponse::item($summary, sprintf('Forecasted %d products.', $summary['forecasted']));
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::item($this->reader->chartFor($product, Carbon::now()->toDateTimeImmutable()));
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::item($this->reader->summary(Carbon::now()->toDateTimeImmutable()));
    }
}
