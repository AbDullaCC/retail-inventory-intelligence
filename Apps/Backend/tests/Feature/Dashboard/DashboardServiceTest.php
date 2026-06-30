<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_inventory_kpis(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id, 'quantity' => 100, 'reorder_level' => 10, 'price' => 2.0, 'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $category->id, 'quantity' => 5, 'reorder_level' => 10, 'price' => 3.0, 'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $category->id, 'quantity' => 0, 'reorder_level' => 10, 'price' => 4.0, 'is_active' => false,
        ]);

        $summary = app(DashboardServiceInterface::class)->summary()->toArray();

        $this->assertSame(3, $summary['total_products']);
        $this->assertSame(2, $summary['active_products']);
        $this->assertSame(1, $summary['total_categories']);
        $this->assertSame(2, $summary['low_stock_count']);
        $this->assertSame(1, $summary['out_of_stock_count']);
        $this->assertSame(105, $summary['total_stock_units']);
        $this->assertSame(215.0, $summary['total_stock_value']);
        $this->assertCount(2, $summary['low_stock_products']);
    }
}
