<?php

declare(strict_types=1);

namespace Tests\Unit\Category;

use App\Modules\Category\Mappers\CategoryMapper;
use App\Modules\Category\Models\Category;
use Tests\TestCase;

class CategoryMapperTest extends TestCase
{
    public function test_it_maps_a_category_with_a_loaded_products_count(): void
    {
        $category = new Category;
        $category->forceFill(['id' => 1, 'name' => 'Snacks', 'description' => 'Tasty']);
        $category->setAttribute('products_count', 4);

        $dto = (new CategoryMapper)->toDTO($category)->toArray();

        $this->assertSame(1, $dto['id']);
        $this->assertSame('Snacks', $dto['name']);
        $this->assertSame(4, $dto['products_count']);
    }

    public function test_products_count_is_null_when_not_loaded(): void
    {
        $category = new Category;
        $category->forceFill(['id' => 2, 'name' => 'Misc', 'description' => null]);

        $dto = (new CategoryMapper)->toDTO($category)->toArray();

        $this->assertNull($dto['products_count']);
        $this->assertNull($dto['description']);
    }
}
