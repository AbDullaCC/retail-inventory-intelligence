<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use App\Modules\Intelligence\Services\ReorderCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReorderCalculatorTest extends TestCase
{
    private ReorderCalculator $calc;

    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ReorderCalculator;
        $this->today = new DateTimeImmutable('2026-07-01');
    }

    /**
     * Anchored case from the spec:
     * currentStock 24, leadTime 7, unitCost 20, 56 units out over 14 days
     * → velocity 4/day, daysOfStockLeft 6, needsReorder true, suggestedReorderQty 84.
     */
    public function test_reorder_case_matches_the_anchored_numbers(): void
    {
        $m = $this->calc->analyze(
            currentStock: 24,
            unitsOutInWindow: 56,
            leadTimeDays: 7,
            unitCost: 20.0,
            today: $this->today,
        );

        $this->assertSame('reorder', $m->type);
        $this->assertEqualsWithDelta(4.0, $m->salesVelocity, 1e-9);
        $this->assertNotNull($m->daysOfStockLeft);
        $this->assertEqualsWithDelta(6.0, $m->daysOfStockLeft, 1e-9);
        $this->assertTrue($m->needsReorder);
        $this->assertSame(84, $m->suggestedReorderQty);
        $this->assertFalse($m->isOverstocked);
        $this->assertEqualsWithDelta(0.0, $m->cashTiedUp, 1e-9);

        // 6 days left < 7-day lead time → already too late, order today (urgent).
        $this->assertTrue($m->isUrgent);
        $this->assertSame('2026-07-01', $m->reorderByDate);

        $this->assertStringContainsString('order 84 units', $m->reasoning);
    }

    public function test_overstock_case(): void
    {
        // 14 out over 14 days → velocity 1/day; 200 on hand → 200 days of cover.
        $m = $this->calc->analyze(
            currentStock: 200,
            unitsOutInWindow: 14,
            leadTimeDays: 7,
            unitCost: 5.0,
            today: $this->today,
        );

        $this->assertSame('overstock', $m->type);
        $this->assertFalse($m->needsReorder);
        $this->assertTrue($m->isOverstocked);
        $this->assertEqualsWithDelta(200.0, $m->daysOfStockLeft, 1e-9);
        // max(0, 200 - 1*30) * 5 = 850
        $this->assertEqualsWithDelta(850.0, $m->cashTiedUp, 1e-9);
        $this->assertStringContainsString('tied up', $m->reasoning);
    }

    public function test_healthy_case(): void
    {
        // 28 out over 14 days → velocity 2/day; 80 on hand → 40 days of cover.
        $m = $this->calc->analyze(
            currentStock: 80,
            unitsOutInWindow: 28,
            leadTimeDays: 7,
            unitCost: 4.0,
            today: $this->today,
        );

        $this->assertSame('healthy', $m->type);
        $this->assertFalse($m->needsReorder);
        $this->assertFalse($m->isOverstocked);
        $this->assertEqualsWithDelta(40.0, $m->daysOfStockLeft, 1e-9);
        // max(0, 80 - 2*30) * 4 = 80
        $this->assertEqualsWithDelta(80.0, $m->cashTiedUp, 1e-9);
    }

    public function test_zero_sales_is_handled_without_dividing_by_zero(): void
    {
        $m = $this->calc->analyze(
            currentStock: 30,
            unitsOutInWindow: 0,
            leadTimeDays: 7,
            unitCost: 5.0,
            today: $this->today,
        );

        $this->assertSame('healthy', $m->type);
        $this->assertSame(0.0, $m->salesVelocity);
        $this->assertNull($m->daysOfStockLeft, 'no recent sales → days-of-stock is undefined');
        $this->assertFalse($m->needsReorder);
        $this->assertFalse($m->isOverstocked);
        $this->assertSame(0, $m->suggestedReorderQty);
        $this->assertNull($m->reorderByDate);
        $this->assertFalse($m->isUrgent);
        // No sales means the whole on-hand value is "excess": 30 * 5 = 150.
        $this->assertEqualsWithDelta(150.0, $m->cashTiedUp, 1e-9);
        $this->assertStringContainsString('No sales', $m->reasoning);
    }

    public function test_velocity_is_not_rounded_in_intermediate_maths(): void
    {
        // 9 over 14 = 0.642857…  — must not be rounded to 1 (or 0).
        $m = $this->calc->analyze(
            currentStock: 50,
            unitsOutInWindow: 9,
            leadTimeDays: 7,
            unitCost: 2.0,
            today: $this->today,
        );

        $this->assertEqualsWithDelta(9 / 14, $m->salesVelocity, 1e-9);
        $this->assertEqualsWithDelta(50 / (9 / 14), $m->daysOfStockLeft, 1e-9);
    }
}
