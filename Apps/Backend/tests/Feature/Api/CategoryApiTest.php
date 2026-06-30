<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_crud_flow(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/categories', ['name' => 'Toys', 'description' => 'Fun stuff'])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Toys');

        $this->putJson("/api/categories/{$id}", ['name' => 'Games'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Games');

        $this->deleteJson("/api/categories/{$id}")->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_delete_with_products_returns_409(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Category::factory()->create(['name' => 'Unique']);

        $this->postJson('/api/categories', ['name' => 'Unique'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
