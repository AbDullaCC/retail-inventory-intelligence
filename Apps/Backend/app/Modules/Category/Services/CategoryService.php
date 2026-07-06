<?php

declare(strict_types=1);

namespace App\Modules\Category\Services;

use App\Modules\Category\DTOs\CategoryData;
use App\Modules\Category\DTOs\CategoryDTO;
use App\Modules\Category\Mappers\CategoryMapper;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\Contracts\CategoryServiceInterface;
use App\Modules\Shared\Exceptions\DomainException;

/**
 * Business logic for the Category module.
 */
final class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryMapper $mapper,
    ) {}

    public function list(): array
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return $this->mapper->toDTOCollection($categories);
    }

    public function find(int $id): CategoryDTO
    {
        $category = Category::query()->withCount('products')->findOrFail($id);

        return $this->mapper->toDTO($category);
    }

    public function create(CategoryData $data): CategoryDTO
    {
        $category = Category::query()->create($this->mapper->toAttributes($data));
        $category->loadCount('products');

        return $this->mapper->toDTO($category);
    }

    public function update(int $id, CategoryData $data): CategoryDTO
    {
        $category = Category::query()->findOrFail($id);
        $category->update($this->mapper->toAttributes($data));
        $category->loadCount('products');

        return $this->mapper->toDTO($category);
    }

    public function delete(int $id): void
    {
        $category = Category::query()->withCount('products')->findOrFail($id);

        if ((int) $category->getAttribute('products_count') > 0) {
            throw new DomainException(
                'Cannot delete a category that still has products. Reassign or remove them first.',
                409,
            );
        }

        $category->delete();
    }
}
