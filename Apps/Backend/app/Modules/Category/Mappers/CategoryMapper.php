<?php

declare(strict_types=1);

namespace App\Modules\Category\Mappers;

use App\Modules\Category\DTOs\CategoryData;
use App\Modules\Category\DTOs\CategoryDTO;
use App\Modules\Category\Models\Category;

/**
 * Converts between the Category model and its DTOs.
 *
 *  - toDTO / toDTOCollection : Model  -> outbound DTO
 *  - toAttributes            : input DTO -> attribute array for persistence
 */
final class CategoryMapper
{
    public function toDTO(Category $category): CategoryDTO
    {
        return new CategoryDTO(
            id: $category->id,
            name: $category->name,
            description: $category->description,
            productsCount: $category->getAttribute('products_count'),
            createdAt: $category->created_at?->toIso8601String(),
            updatedAt: $category->updated_at?->toIso8601String(),
        );
    }

    /**
     * @param  iterable<Category>  $categories
     * @return array<int, CategoryDTO>
     */
    public function toDTOCollection(iterable $categories): array
    {
        $dtos = [];

        foreach ($categories as $category) {
            $dtos[] = $this->toDTO($category);
        }

        return $dtos;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(CategoryData $data): array
    {
        return [
            'name' => $data->name,
            'description' => $data->description,
        ];
    }
}
