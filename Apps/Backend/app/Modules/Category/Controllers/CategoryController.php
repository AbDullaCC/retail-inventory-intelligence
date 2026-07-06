<?php

namespace App\Modules\Category\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Category\DTOs\CategoryData;
use App\Modules\Category\Requests\StoreCategoryRequest;
use App\Modules\Category\Requests\UpdateCategoryRequest;
use App\Modules\Category\Services\Contracts\CategoryServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryServiceInterface $service,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::collection($this->service->list());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(CategoryData::fromArray($request->validated()));

        return ApiResponse::item($category, 'Category created.', 201);
    }

    public function show(int $category): JsonResponse
    {
        return ApiResponse::item($this->service->find($category));
    }

    public function update(UpdateCategoryRequest $request, int $category): JsonResponse
    {
        $updated = $this->service->update($category, CategoryData::fromArray($request->validated()));

        return ApiResponse::item($updated, 'Category updated.');
    }

    public function destroy(int $category): JsonResponse
    {
        $this->service->delete($category);

        return ApiResponse::message('Category deleted.');
    }
}
