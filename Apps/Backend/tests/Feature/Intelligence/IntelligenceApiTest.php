<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedReorderProduct(): Product
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 24,
            'cost' => 20.0,
        ]);

        /** @var StockMovement $m */
        $m = StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => null,
            'type' => StockMovementType::Out,
            'quantity' => 56,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'reason' => 'sale',
        ]);
        $when = Carbon::now()->subDays(3);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);

        return $product;
    }

    public function test_recommendations_endpoint_requires_auth(): void
    {
        $this->getJson('/api/intelligence/recommendations')->assertUnauthorized();
    }

    public function test_recommendations_endpoint_returns_aggregates_and_list(): void
    {
        $this->seedReorderProduct();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/intelligence/recommendations')
            ->assertOk()
            ->assertJsonPath('data.reorder_count', 1)
            ->assertJsonPath('data.recommendations.0.type', 'reorder')
            ->assertJsonPath('data.recommendations.0.suggested_reorder_qty', 84)
            ->assertJsonStructure([
                'data' => [
                    'reorder_count',
                    'overstock_count',
                    'healthy_count',
                    'total_cash_tied_up',
                    'velocity_window_days',
                    'default_lead_time_days',
                    'generated_at',
                    'recommendations' => [
                        ['product_id', 'sku', 'type', 'sales_velocity', 'days_of_stock_left', 'reasoning'],
                    ],
                ],
            ]);
    }

    public function test_single_product_recommendation_endpoint(): void
    {
        $product = $this->seedReorderProduct();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/products/{$product->id}/recommendation")
            ->assertOk()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.type', 'reorder')
            ->assertJsonPath('data.suggested_reorder_qty', 84)
            ->assertJsonPath('data.needs_reorder', true);
    }

    public function test_single_product_recommendation_404_for_missing_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/999999/recommendation')->assertNotFound();
    }
}
