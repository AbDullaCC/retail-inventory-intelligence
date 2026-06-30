<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use App\Modules\Category\Mappers\CategoryMapper;
use App\Modules\Category\Models\Category;
use App\Modules\Product\Mappers\ProductMapper;
use App\Modules\Product\Models\Product;
use Tests\TestCase;

class ProductMapperTest extends TestCase
{
    private function mapper(): ProductMapper
    {
        return new ProductMapper(new CategoryMapper());
    }

    public function test_it_maps_a_product_to_a_dto_with_derived_fields(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'category_id' => 7,
            'sku' => 'BEV-001',
            'name' => 'Cola',
            'description' => 'Fizzy',
            'price' => 2.5,
            'cost' => 1.0,
            'quantity' => 3,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        $dto = $this->mapper()->toDTO($product)->toArray();

        $this->assertSame(2.5, $dto['price']);
        $this->assertSame(7, $dto['category_id']);
        $this->assertTrue($dto['is_low_stock'], '3 units <= reorder level 5 is low stock');
        $this->assertSame(7.5, $dto['stock_value'], 'stock_value = price * quantity');
        $this->assertNull($dto['category'], 'category is null when the relation is not loaded');
    }

    public function test_it_includes_the_nested_category_when_loaded(): void
    {
        $category = new Category();
        $category->forceFill(['id' => 7, 'name' => 'Beverages', 'description' => null]);

        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'category_id' => 7,
            'sku' => 'BEV-001',
            'name' => 'Cola',
            'price' => 2.5,
            'cost' => null,
            'quantity' => 40,
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $product->setRelation('category', $category);

        $dto = $this->mapper()->toDTO($product)->toArray();

        $this->assertFalse($dto['is_low_stock']);
        $this->assertNull($dto['cost']);
        $this->assertSame('Beverages', $dto['category']['name']);
    }
}
