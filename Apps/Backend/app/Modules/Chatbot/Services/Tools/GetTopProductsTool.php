<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;

/**
 * `get_top_products` — best sellers over a trailing window, ranked by units
 * actually sold in the ledger. THE tool for "what sold the most?"; the
 * recent-movements sample must never be used for rankings.
 */
final class GetTopProductsTool
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_top_products',
            description: 'Best-selling products over the last N days (default 7, max 90), ranked by units actually sold, with revenue estimated at the current price. Use for "top sellers", "what sold the most last week/month?", "best products". Do NOT answer those questions from get_recent_movements — that is only a small sample of the latest ledger rows.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'description' => 'Window length in days (default 7).'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'description' => 'How many products (default 5).'],
                ],
            ],
            handler: function (array $args): array {
                $days = max(1, min(90, (int) ($args['days'] ?? 7)));
                $limit = max(1, min(20, (int) ($args['limit'] ?? 5)));

                return [
                    'days' => $days,
                    'top_products' => $this->dashboard->topProducts($days, $limit),
                    'note' => 'revenue is estimated at the current product price',
                ];
            },
        );
    }
}
