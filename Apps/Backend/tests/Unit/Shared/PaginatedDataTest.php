<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Modules\Shared\DTOs\PaginatedData;
use PHPUnit\Framework\TestCase;

class PaginatedDataTest extends TestCase
{
    public function test_it_computes_from_and_to_for_a_middle_page(): void
    {
        $data = new PaginatedData(
            items: [['id' => 3], ['id' => 4]],
            total: 10,
            perPage: 2,
            currentPage: 2,
            lastPage: 5,
        );

        $array = $data->toArray();

        $this->assertSame([['id' => 3], ['id' => 4]], $array['data']);
        $this->assertSame(10, $array['meta']['total']);
        $this->assertSame(3, $array['meta']['from']);
        $this->assertSame(4, $array['meta']['to']);
        $this->assertSame(2, $array['meta']['current_page']);
        $this->assertSame(5, $array['meta']['last_page']);
    }

    public function test_empty_result_has_null_from_and_to(): void
    {
        $data = new PaginatedData(items: [], total: 0, perPage: 10, currentPage: 1, lastPage: 1);

        $array = $data->toArray();

        $this->assertSame([], $array['data']);
        $this->assertNull($array['meta']['from']);
        $this->assertNull($array['meta']['to']);
    }

    public function test_to_is_clamped_to_the_total_on_the_last_page(): void
    {
        $data = new PaginatedData(items: [['id' => 1]], total: 7, perPage: 3, currentPage: 3, lastPage: 3);

        $this->assertSame(7, $data->toArray()['meta']['from']);
        $this->assertSame(7, $data->toArray()['meta']['to']);
    }
}
