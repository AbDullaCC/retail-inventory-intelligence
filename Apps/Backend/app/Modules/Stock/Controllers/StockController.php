<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\Requests\AdjustStockRequest;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StockController extends Controller
{
    public function __construct(
        private readonly StockServiceInterface $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        return ApiResponse::collection($this->service->recent($limit));
    }

    public function history(Request $request, int $product): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        return ApiResponse::paginated($this->service->history($product, $perPage, $page));
    }

    public function adjust(AdjustStockRequest $request, int $product): JsonResponse
    {
        $movement = $this->service->adjust(
            $product,
            StockAdjustmentData::fromArray($request->validated()),
            $request->user()->id,
        );

        return ApiResponse::item($movement, 'Stock updated successfully.', 201);
    }
}
