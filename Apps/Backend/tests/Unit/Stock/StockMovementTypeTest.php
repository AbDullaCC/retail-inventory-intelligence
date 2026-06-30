<?php

declare(strict_types=1);

namespace Tests\Unit\Stock;

use App\Modules\Stock\Enums\StockMovementType;
use PHPUnit\Framework\TestCase;

class StockMovementTypeTest extends TestCase
{
    public function test_values_returns_every_case(): void
    {
        $this->assertSame(['in', 'out', 'adjustment'], StockMovementType::values());
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Stock In', StockMovementType::In->label());
        $this->assertSame('Stock Out', StockMovementType::Out->label());
        $this->assertSame('Adjustment', StockMovementType::Adjustment->label());
    }

    public function test_can_be_created_from_a_string(): void
    {
        $this->assertSame(StockMovementType::Out, StockMovementType::from('out'));
    }
}
