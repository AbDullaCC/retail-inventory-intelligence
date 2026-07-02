<?php

namespace App\Modules\Forecast\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class ForecastController extends Controller
{
    public function __construct(
        private readonly ForecastReaderInterface $reader,
    ) {}

    public function show(int $product): JsonResponse
    {
        return ApiResponse::item($this->reader->chartFor($product, Carbon::now()->toDateTimeImmutable()));
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::item($this->reader->summary(Carbon::now()->toDateTimeImmutable()));
    }
}
