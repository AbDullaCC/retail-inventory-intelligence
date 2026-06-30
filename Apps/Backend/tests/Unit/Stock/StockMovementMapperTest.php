<?php

declare(strict_types=1);

namespace Tests\Unit\Stock;

use App\Modules\Stock\Mappers\StockMovementMapper;
use App\Modules\Stock\Models\StockMovement;
use Tests\TestCase;

class StockMovementMapperTest extends TestCase
{
    public function test_it_maps_a_movement_and_computes_the_change(): void
    {
        $movement = new StockMovement();
        $movement->forceFill([
            'id' => 1,
            'product_id' => 9,
            'user_id' => 3,
            'type' => 'out',
            'quantity' => 5,
            'quantity_before' => 12,
            'quantity_after' => 7,
            'reason' => 'Customer sale',
        ]);

        $dto = (new StockMovementMapper())->toDTO($movement)->toArray();

        $this->assertSame('out', $dto['type']);
        $this->assertSame('Stock Out', $dto['type_label']);
        $this->assertSame(-5, $dto['change'], 'change = after - before');
        $this->assertSame(9, $dto['product_id']);
        $this->assertSame('Customer sale', $dto['reason']);
        $this->assertNull($dto['product_name'], 'no product name without the relation loaded');
        $this->assertNull($dto['user_name']);
    }
}
