<?php

declare(strict_types=1);

namespace App\Modules\Category\DTOs;

use App\Modules\Shared\DTOs\BaseData;

final class CategoryDTO extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?int $productsCount,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'products_count' => $this->productsCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
