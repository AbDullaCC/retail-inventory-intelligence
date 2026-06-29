<?php

declare(strict_types=1);

namespace App\Modules\Product\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Input DTO for creating/updating a product's own attributes.
 *
 * Note: stock `quantity` is intentionally NOT part of this DTO — on-hand stock is
 * only ever changed through the Stock module so every change leaves an audit trail.
 */
final class ProductData extends BaseData
{
    public function __construct(
        public readonly int $categoryId,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly ?float $cost,
        public readonly int $reorderLevel,
        public readonly bool $isActive,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: (int) $data['category_id'],
            sku: (string) $data['sku'],
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: (float) $data['price'],
            cost: isset($data['cost']) && $data['cost'] !== null ? (float) $data['cost'] : null,
            reorderLevel: (int) ($data['reorder_level'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'cost' => $this->cost,
            'reorder_level' => $this->reorderLevel,
            'is_active' => $this->isActive,
        ];
    }
}
