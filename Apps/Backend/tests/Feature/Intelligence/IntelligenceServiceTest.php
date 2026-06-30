<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Modules\Category\Models\Category;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntelligenceServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
        $this->service = app(IntelligenceServiceInterface::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function movement(int $productId, StockMovementType $type, int $qty, int $daysAgo): void
    {
        /** @var StockMovement $m */
        $m = StockMovement::query()->create([
            'product_id' => $productId,
            'user_id' => null,
            'type' => $type,
            'quantity' => $qty,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'reason' => 'test',
        ]);

        $when = Carbon::now()->subDays($daysAgo);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $when, 'updated_at' => $when]);
    }

    public function test_velocity_is_derived_only_from_out_movements_inside_the_window(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 24,
            'cost' => 20.0,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        // 56 units sold inside the 14-day window (30 two days ago + 26 ten days ago).
        $this->movement($product->id, StockMovementType::Out, 30, 2);
        $this->movement($product->id, StockMovementType::Out, 26, 10);

        // Noise that must be ignored:
        $this->movement($product->id, StockMovementType::Out, 100, 40); // out, but outside window
        $this->movement($product->id, StockMovementType::In, 500, 1);   // restock inside window
        $this->movement($product->id, StockMovementType::Adjustment, 9, 1); // adjustment inside window

        $dto = $this->service->forProduct($product->id);

        $this->assertEqualsWithDelta(4.0, $dto->salesVelocity, 1e-9, 'only the 56 in-window OUT units count');
        $this->assertEqualsWithDelta(6.0, $dto->daysOfStockLeft, 1e-9);
        $this->assertTrue($dto->needsReorder);
        $this->assertSame(84, $dto->suggestedReorderQty);
        $this->assertSame('reorder', $dto->type);
        $this->assertSame(7, $dto->leadTimeDays);
        $this->assertTrue($dto->leadTimeIsDefault);
        $this->assertFalse($dto->unitCostIsDefault);
    }

    public function test_null_cost_falls_back_to_the_default_and_is_flagged(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 10,
            'cost' => null,
        ]);

        $dto = $this->service->forProduct($product->id);

        $this->assertTrue($dto->unitCostIsDefault);
        $this->assertSame(0.0, $dto->unitCost);
        // velocity 0 → cash tied up = stock * default cost (0) = 0
        $this->assertSame(0.0, $dto->cashTiedUp);
    }

    public function test_aggregate_counts_each_recommendation_type(): void
    {
        $category = Category::factory()->create();

        // reorder: 24 on hand, 56 out in window, velocity 4 → 6 days left
        $reorder = Product::factory()->create(['category_id' => $category->id, 'quantity' => 24, 'cost' => 20.0]);
        $this->movement($reorder->id, StockMovementType::Out, 56, 3);

        // overstock: 200 on hand, 14 out in window, velocity 1 → 200 days left
        $overstock = Product::factory()->create(['category_id' => $category->id, 'quantity' => 200, 'cost' => 5.0]);
        $this->movement($overstock->id, StockMovementType::Out, 14, 5);

        // healthy: 80 on hand, 28 out in window, velocity 2 → 40 days left
        $healthy = Product::factory()->create(['category_id' => $category->id, 'quantity' => 80, 'cost' => 4.0]);
        $this->movement($healthy->id, StockMovementType::Out, 28, 6);

        $summary = $this->service->recommendations();

        $this->assertSame(1, $summary->reorderCount);
        $this->assertSame(1, $summary->overstockCount);
        $this->assertSame(1, $summary->healthyCount);
        $this->assertCount(3, $summary->recommendations);
        // overstock: max(0, 200 - 1*30) * 5 = 850;  healthy: max(0, 80 - 2*30) * 4 = 80;  reorder: 0
        $this->assertEqualsWithDelta(930.0, $summary->totalCashTiedUp, 1e-9);
    }
}
