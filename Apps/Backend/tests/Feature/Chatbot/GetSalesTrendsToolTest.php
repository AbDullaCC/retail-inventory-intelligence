<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Category\Models\Category;
use App\Modules\Chatbot\Services\Tools\GetSalesTrendsTool;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The get_sales_trends tool must report zero-activity dates server-side for
 * the whole window — the assistant answers "which dates had no movements?"
 * from this field, never by scanning day rows itself.
 */
class GetSalesTrendsToolTest extends TestCase
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

    public function test_reports_zero_movement_dates_for_the_full_window(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);
        // Activity on Jun 29 and Jul 1 only — the other five days of the
        // 7-day window (Jun 25..Jul 1) are quiet.
        $this->movement($product->id, '2026-06-29 10:00:00');
        $this->movement($product->id, '2026-07-01 09:00:00');

        $result = app(GetSalesTrendsTool::class)->build()->run(['days' => 7]);

        $this->assertSame(5, $result['days_with_zero_movements']['count']);
        $this->assertSame(
            ['2026-06-25', '2026-06-26', '2026-06-27', '2026-06-28', '2026-06-30'],
            $result['days_with_zero_movements']['dates'],
        );
        // Present even for long windows, where day-by-day rows are omitted.
        $long = app(GetSalesTrendsTool::class)->build()->run(['days' => 60]);
        $this->assertArrayNotHasKey('daily', $long);
        $this->assertSame(58, $long['days_with_zero_movements']['count']);
    }

    private function movement(int $productId, string $at): void
    {
        $m = StockMovement::query()->create([
            'product_id' => $productId,
            'user_id' => null,
            'type' => StockMovementType::Out,
            'quantity' => 1,
            'quantity_before' => 10,
            'quantity_after' => 9,
            'reason' => 'test',
        ]);
        StockMovement::query()->whereKey($m->id)->update(['created_at' => $at, 'updated_at' => $at]);
    }
}
