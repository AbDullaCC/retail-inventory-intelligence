<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Product\DTOs\ProductData;
use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductServiceInterface::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function data(int $categoryId, array $overrides = []): ProductData
    {
        return ProductData::fromArray(array_merge([
            'category_id' => $categoryId,
            'sku' => 'SKU-'.uniqid(),
            'name' => 'Widget',
            'description' => null,
            'price' => 10.0,
            'cost' => 4.0,
            'reorder_level' => 5,
            'is_active' => true,
        ], $overrides));
    }

    public function test_create_without_opening_stock_logs_no_movement(): void
    {
        $category = Category::factory()->create();

        $dto = $this->service->create($this->data($category->id), 0, null);

        $this->assertSame(0, $dto->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_create_with_opening_stock_records_a_movement(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();

        $dto = $this->service->create($this->data($category->id), 50, $user->id);

        $this->assertSame(50, $dto->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $dto->id,
            'type' => 'in',
            'quantity' => 50,
            'quantity_before' => 0,
            'quantity_after' => 50,
            'user_id' => $user->id,
        ]);
    }

    public function test_update_changes_attributes_but_never_the_quantity(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'quantity' => 30]);

        $dto = $this->service->update(
            $product->id,
            $this->data($category->id, ['name' => 'Renamed', 'sku' => $product->sku]),
        );

        $this->assertSame('Renamed', $dto->name);
        $this->assertSame(30, $dto->quantity, 'stock is not touched by a product update');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_paginate_applies_search_and_low_stock_filters(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id, 'name' => 'Apple Juice', 'quantity' => 100, 'reorder_level' => 10,
        ]);
        Product::factory()->create([
            'category_id' => $category->id, 'name' => 'Banana Chips', 'quantity' => 2, 'reorder_level' => 10,
        ]);

        $search = $this->service->paginate(ProductFilterData::fromArray(['search' => 'Apple']));
        $this->assertSame(1, $search->total);

        $low = $this->service->paginate(ProductFilterData::fromArray(['low_stock' => 'true']));
        $this->assertSame(1, $low->total);
    }

    public function test_delete_removes_the_product_and_its_movements(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        $dto = $this->service->create($this->data($category->id), 10, $user->id);

        $this->service->delete($dto->id);

        $this->assertDatabaseMissing('products', ['id' => $dto->id]);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
