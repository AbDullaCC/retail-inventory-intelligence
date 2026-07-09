<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Stock\Services\Contracts\StockServiceInterface;

/**
 * `get_recent_movements` — recent stock ledger activity. With `product_id`,
 * returns that product's paginated history; otherwise the most recent
 * store-wide movements. Truncated to the context cap.
 */
final class GetRecentMovementsTool
{
    public function __construct(
        private readonly StockServiceInterface $stock,
        private readonly int $maxItems,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_recent_movements',
            description: 'Recent stock movements from the ledger. Without `product_id`: the most recent store-wide movements (in/out/adjustment). With `product_id`: that product\'s movement history. Optional `limit` (default 10, max 50). Use for "what happened recently?" or "show me stock changes for product X".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Narrow to one product\'s history.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                ],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));

                if (isset($args['product_id'])) {
                    $page = $this->stock->history((int) $args['product_id'], $limit, 1);
                    $payload = $page->toArray();
                    $truncated = ToolResultTruncator::truncate($payload['data'], $this->maxItems);

                    return [
                        'movements' => $truncated['items'],
                        'total' => $truncated['total'],
                        'returned' => count($truncated['items']),
                        'truncated' => $truncated['truncated'],
                        'meta' => $payload['meta'],
                    ];
                }

                $movements = $this->stock->recent($limit);
                $truncated = ToolResultTruncator::truncate(
                    array_map(static fn ($m) => $m->toArray(), $movements),
                    $this->maxItems,
                );

                return [
                    'movements' => $truncated['items'],
                    'total' => $truncated['total'],
                    'returned' => count($truncated['items']),
                    'truncated' => $truncated['truncated'],
                ];
            },
        );
    }
}
