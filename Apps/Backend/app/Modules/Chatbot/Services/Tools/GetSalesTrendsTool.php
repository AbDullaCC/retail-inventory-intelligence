<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;

/**
 * `get_sales_trends` — the dashboard trends feed: zero-filled daily in/out
 * movement totals over a trailing window plus stock value by category. The
 * series is capped to the context limit (oldest days dropped first) so a 90-day
 * request doesn't dump 90 points into the LLM.
 */
final class GetSalesTrendsTool
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
        private readonly int $maxItems,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_sales_trends',
            description: 'Sales/movement trends over a trailing window: zero-filled daily units-in and units-out totals, plus current stock value by category. Optional `days` (7-90, default 30) and `product_id` to narrow to one product (the category breakdown is then empty). Use for "how are sales trending?" or "show me the last month\'s movement".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 90, 'default' => 30],
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                $days = max(7, min(90, (int) ($args['days'] ?? 30)));
                $productId = isset($args['product_id']) ? (int) $args['product_id'] : null;

                $trends = $this->dashboard->trends($days, $productId)->toArray();
                $seriesTruncated = ToolResultTruncator::truncate($trends['series'], $this->maxItems);
                $catTruncated = ToolResultTruncator::truncate($trends['category_values'], $this->maxItems);

                return [
                    'days' => $trends['days'],
                    'series' => $seriesTruncated['items'],
                    'series_total' => $seriesTruncated['total'],
                    'series_truncated' => $seriesTruncated['truncated'],
                    'category_values' => $catTruncated['items'],
                    'category_values_total' => $catTruncated['total'],
                ];
            },
        );
    }
}
