<?php

namespace App\Modules\Intelligence\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class IntelligenceController extends Controller
{
    public function __construct(
        private readonly IntelligenceServiceInterface $service,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::item($this->service->recommendations());
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::item($this->service->forProduct($product));
    }
}
