<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Services\Tools\ToolResultTruncator;
use PHPUnit\Framework\TestCase;

final class ToolResultTruncatorTest extends TestCase
{
    public function test_caps_lists_and_marks_truncation(): void
    {
        $capped = ToolResultTruncator::cap(range(1, 30), 20);

        $this->assertCount(20, $capped['items']);
        $this->assertSame(30, $capped['total']);
        $this->assertTrue($capped['truncated']);
    }

    public function test_leaves_short_lists_alone(): void
    {
        $capped = ToolResultTruncator::cap([1, 2, 3], 20);

        $this->assertSame([1, 2, 3], $capped['items']);
        $this->assertFalse($capped['truncated']);
    }

    public function test_summary_reports_list_counts_and_truncation(): void
    {
        $summary = ToolResultTruncator::summarise('get_recommendations', [
            'recommendations' => range(1, 20),
            'total' => 55,
        ]);

        $this->assertSame('get_recommendations: 20 of 55 recommendations', $summary);
    }

    public function test_summary_relays_error_payloads(): void
    {
        $summary = ToolResultTruncator::summarise('find_product', ['error' => 'No matching record']);

        $this->assertSame('find_product: No matching record', $summary);
    }

    public function test_summary_describes_scalar_results_by_field_count(): void
    {
        $summary = ToolResultTruncator::summarise('get_store_overview', [
            'total_products' => 276,
            'total_stock_value' => 12345.67,
        ]);

        $this->assertSame('get_store_overview: 2 fields', $summary);
    }
}
