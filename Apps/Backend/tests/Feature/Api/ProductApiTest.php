<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_index_returns_paginated_products(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'sku', 'name', 'price', 'quantity', 'is_low_stock', 'stock_value', 'category']],
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_a_product_with_opening_stock(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();

        $this->postJson('/api/products', [
            'category_id' => $category->id,
            'sku' => 'NEW-1',
            'name' => 'New Widget',
            'price' => 9.99,
            'cost' => 4.50,
            'reorder_level' => 3,
            'quantity' => 20,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'NEW-1')
            ->assertJsonPath('data.quantity', 20);

        $this->assertDatabaseHas('stock_movements', ['type' => 'in', 'quantity' => 20]);
    }

    public function test_store_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/products', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'sku', 'name', 'price']);
    }

    public function test_show_returns_404_for_a_missing_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/999999')->assertNotFound();
    }

    public function test_update_changes_attributes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->putJson("/api/products/{$product->id}", [
            'category_id' => $category->id,
            'sku' => $product->sku,
            'name' => 'Updated Name',
            'price' => 1.00,
            'reorder_level' => 2,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_deletes_a_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
