<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use App\Modules\Product\DTOs\ProductFilterData;
use PHPUnit\Framework\TestCase;

class ProductFilterDataTest extends TestCase
{
    public function test_it_parses_and_sanitises_query_input(): void
    {
        $filter = ProductFilterData::fromArray([
            'search' => '  cola ',
            'category_id' => '3',
            'low_stock' => 'true',
            'is_active' => '0',
            'sort_by' => 'price',
            'sort_dir' => 'DESC',
            'per_page' => '500',
            'page' => '2',
        ]);

        $this->assertSame('cola', $filter->search);
        $this->assertSame(3, $filter->categoryId);
        $this->assertTrue($filter->lowStock);
        $this->assertFalse($filter->isActive);
        $this->assertSame('price', $filter->sortBy);
        $this->assertSame('desc', $filter->sortDir);
        $this->assertSame(100, $filter->perPage, 'per_page must be clamped to 100');
        $this->assertSame(2, $filter->page);
    }

    public function test_it_rejects_unknown_sort_columns_to_prevent_injection(): void
    {
        $filter = ProductFilterData::fromArray(['sort_by' => 'price); DROP TABLE products;--']);

        $this->assertSame('name', $filter->sortBy);
        $this->assertSame('asc', $filter->sortDir);
    }

    public function test_defaults_when_nothing_is_provided(): void
    {
        $filter = ProductFilterData::fromArray([]);

        $this->assertNull($filter->search);
        $this->assertNull($filter->categoryId);
        $this->assertNull($filter->lowStock);
        $this->assertNull($filter->isActive);
        $this->assertSame(15, $filter->perPage);
        $this->assertSame(1, $filter->page);
    }
}
