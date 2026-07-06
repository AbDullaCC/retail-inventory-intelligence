<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Modules\Product\DTOs\ProductData;
use App\Modules\Product\DTOs\ProductDTO;
use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Product\Mappers\ProductMapper;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use App\Modules\Shared\DTOs\PaginatedData;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Product module.
 *
 * Product attribute changes live here; all on-hand stock changes are delegated to
 * the Stock module so there is a single, audited source of truth for inventory.
 */
final class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductMapper $mapper,
        private readonly StockServiceInterface $stockService,
    ) {}

    public function paginate(ProductFilterData $filter): PaginatedData
    {
        $query = Product::query()->with('category');

        if ($filter->search !== null) {
            $term = '%'.$filter->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            });
        }

        if ($filter->categoryId !== null) {
            $query->where('category_id', $filter->categoryId);
        }

        if ($filter->isActive !== null) {
            $query->where('is_active', $filter->isActive);
        }

        if ($filter->lowStock === true) {
            $query->whereColumn('quantity', '<=', 'reorder_level');
        }

        $query->orderBy($filter->sortBy, $filter->sortDir);

        $paginator = $query->paginate(perPage: $filter->perPage, page: $filter->page);

        return PaginatedData::fromPaginator($paginator, fn (Product $product) => $this->mapper->toDTO($product));
    }

    public function find(int $id): ProductDTO
    {
        $product = Product::query()->with('category')->findOrFail($id);

        return $this->mapper->toDTO($product);
    }

    public function create(ProductData $data, int $initialQuantity = 0, ?int $userId = null): ProductDTO
    {
        $product = DB::transaction(function () use ($data, $initialQuantity, $userId): Product {
            $attributes = $this->mapper->toAttributes($data);
            $attributes['quantity'] = 0;

            $product = Product::query()->create($attributes);

            if ($initialQuantity > 0) {
                // Delegate opening stock to the Stock module so it is recorded as a movement.
                $this->stockService->adjust(
                    $product->id,
                    new StockAdjustmentData(StockMovementType::In, $initialQuantity, 'Opening stock'),
                    $userId,
                );
                $product->refresh();
            }

            return $product;
        });

        $product->load('category');

        return $this->mapper->toDTO($product);
    }

    public function update(int $id, ProductData $data): ProductDTO
    {
        $product = Product::query()->findOrFail($id);
        $product->update($this->mapper->toAttributes($data));
        $product->load('category');

        return $this->mapper->toDTO($product);
    }

    public function delete(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $product->delete();
    }
}
