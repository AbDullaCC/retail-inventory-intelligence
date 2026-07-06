<?php

declare(strict_types=1);

namespace App\Modules\Product\DTOs;

use App\Modules\Category\DTOs\CategoryDTO;
use App\Modules\Shared\DTOs\BaseData;

final class ProductDTO extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly ?float $cost,
        public readonly int $quantity,
        public readonly int $reorderLevel,
        public readonly bool $isActive,
        public readonly bool $isLowStock,
        public readonly float $stockValue,
        public readonly ?CategoryDTO $category,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'cost' => $this->cost,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorderLevel,
            'is_active' => $this->isActive,
            'is_low_stock' => $this->isLowStock,
            'stock_value' => $this->stockValue,
            'category' => $this->category?->toArray(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
