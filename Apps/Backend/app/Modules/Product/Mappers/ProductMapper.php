<?php

declare(strict_types=1);

namespace App\Modules\Product\Mappers;

use App\Modules\Category\Mappers\CategoryMapper;
use App\Modules\Product\DTOs\ProductData;
use App\Modules\Product\DTOs\ProductDTO;
use App\Modules\Product\Models\Product;

final class ProductMapper
{
    public function __construct(
        private readonly CategoryMapper $categoryMapper,
    ) {
    }

    public function toDTO(Product $product): ProductDTO
    {
        $price = (float) $product->price;
        $quantity = (int) $product->quantity;

        $category = $product->relationLoaded('category') && $product->category !== null
            ? $this->categoryMapper->toDTO($product->category)
            : null;

        return new ProductDTO(
            id: $product->id,
            categoryId: (int) $product->category_id,
            sku: $product->sku,
            name: $product->name,
            description: $product->description,
            price: $price,
            cost: $product->cost !== null ? (float) $product->cost : null,
            quantity: $quantity,
            reorderLevel: (int) $product->reorder_level,
            isActive: (bool) $product->is_active,
            isLowStock: $quantity <= (int) $product->reorder_level,
            stockValue: round($price * $quantity, 2),
            category: $category,
            createdAt: $product->created_at?->toIso8601String(),
            updatedAt: $product->updated_at?->toIso8601String(),
        );
    }

    /**
     * @param  iterable<Product>  $products
     * @return array<int, ProductDTO>
     */
    public function toDTOCollection(iterable $products): array
    {
        $dtos = [];

        foreach ($products as $product) {
            $dtos[] = $this->toDTO($product);
        }

        return $dtos;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(ProductData $data): array
    {
        return [
            'category_id' => $data->categoryId,
            'sku' => $data->sku,
            'name' => $data->name,
            'description' => $data->description,
            'price' => $data->price,
            'cost' => $data->cost,
            'reorder_level' => $data->reorderLevel,
            'is_active' => $data->isActive,
        ];
    }
}
