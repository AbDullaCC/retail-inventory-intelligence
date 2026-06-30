<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Models\User;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjust_increments_stock(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['quantity' => 10]);

        $this->postJson("/api/products/{$product->id}/stock-adjustments", [
            'type' => 'in',
            'quantity' => 5,
            'reason' => 'restock',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_after', 15)
            ->assertJsonPath('data.change', 5);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 15]);
    }

    public function test_adjust_out_beyond_available_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['quantity' => 3]);

        $response = $this->postJson("/api/products/{$product->id}/stock-adjustments", [
            'type' => 'out',
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Insufficient', (string) $response->json('message'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
    }

    public function test_adjust_rejects_an_unknown_movement_type(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->id}/stock-adjustments", ['type' => 'banana', 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_history_returns_movements(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['quantity' => 0]);
        $this->postJson("/api/products/{$product->id}/stock-adjustments", ['type' => 'in', 'quantity' => 5]);

        $this->getJson("/api/products/{$product->id}/stock-movements")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'quantity_before', 'quantity_after', 'change']],
                'meta' => ['total'],
            ]);
    }

    public function test_adjustment_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->id}/stock-adjustments", ['type' => 'in', 'quantity' => 1])
            ->assertUnauthorized();
    }
}
