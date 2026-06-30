<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Modules\Product\Models\Product;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Exceptions\InsufficientStockException;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockServiceInterface::class);
    }

    public function test_stock_in_increases_quantity_and_logs_a_movement(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $dto = $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::In, 5, 'restock'));

        $this->assertSame(10, $dto->quantityBefore);
        $this->assertSame(15, $dto->quantityAfter);
        $this->assertSame(5, $dto->change);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 15]);
    }

    public function test_stock_out_decreases_quantity(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $dto = $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::Out, 4));

        $this->assertSame(6, $dto->quantityAfter);
    }

    public function test_adjustment_sets_the_exact_quantity(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $dto = $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::Adjustment, 3));

        $this->assertSame(3, $dto->quantityAfter);
        $this->assertSame(-7, $dto->change);
    }

    public function test_removing_more_than_available_throws_and_does_not_mutate(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        try {
            $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::Out, 99));
            $this->fail('Expected an InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(422, $e->status());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_history_and_recent_return_movements_newest_first(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::In, 5));
        $this->service->adjust($product->id, new StockAdjustmentData(StockMovementType::Out, 2));

        $history = $this->service->history($product->id);
        $this->assertSame(2, $history->total);

        $recent = $this->service->recent(10);
        $this->assertCount(2, $recent);
        $this->assertSame('out', $recent[0]->type, 'most recent movement first');
    }
}
