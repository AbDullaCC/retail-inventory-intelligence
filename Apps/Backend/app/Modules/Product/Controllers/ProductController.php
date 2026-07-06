<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\DTOs\ProductData;
use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Product\Requests\StoreProductRequest;
use App\Modules\Product\Requests\UpdateProductRequest;
use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductServiceInterface $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filter = ProductFilterData::fromArray($request->query());

        return ApiResponse::paginated($this->service->paginate($filter));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = $this->service->create(
            ProductData::fromArray($validated),
            (int) ($validated['quantity'] ?? 0),
            $request->user()->id,
        );

        return ApiResponse::item($product, 'Product created.', 201);
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::item($this->service->find($product));
    }

    public function update(UpdateProductRequest $request, int $product): JsonResponse
    {
        $updated = $this->service->update($product, ProductData::fromArray($request->validated()));

        return ApiResponse::item($updated, 'Product updated.');
    }

    public function destroy(int $product): JsonResponse
    {
        $this->service->delete($product);

        return ApiResponse::message('Product deleted.');
    }
}
