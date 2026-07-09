<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Services\Tools\ToolResultTruncator;
use App\Modules\Chatbot\Services\Tools\ToolResultSummary;
use PHPUnit\Framework\TestCase;

class ToolResultTruncatorTest extends TestCase
{
    public function test_caps_lists_at_the_limit_and_marks_truncation(): void
    {
        $items = range(1, 250);

        $result = ToolResultTruncator::truncate($items, 20);

        $this->assertCount(20, $result['items']);
        $this->assertSame(250, $result['total']);
        $this->assertTrue($result['truncated']);
    }

    public function test_does_not_truncate_when_under_the_limit(): void
    {
        $items = range(1, 5);

        $result = ToolResultTruncator::truncate($items, 20);

        $this->assertCount(5, $result['items']);
        $this->assertSame(5, $result['total']);
        $this->assertFalse($result['truncated']);
    }

    public function test_summary_describes_item_results_with_count_and_truncation(): void
    {
        $truncated = ToolResultTruncator::truncate(range(1, 100), 20);

        $this->assertSame(
            'get_reorder_recommendations returned 100 items (truncated).',
            ToolResultSummary::summarise('get_reorder_recommendations', $truncated),
        );
    }

    public function test_summary_relays_error_payloads(): void
    {
        $this->assertSame(
            'Product not found',
            ToolResultSummary::summarise('get_product', ['error' => 'product not found']),
        );
    }

    public function test_summary_describes_object_results_by_field_count(): void
    {
        $this->assertSame(
            'get_store_overview returned 2 fields.',
            ToolResultSummary::summarise('get_store_overview', ['kpi' => [], 'verdicts' => []]),
        );
    }
}
