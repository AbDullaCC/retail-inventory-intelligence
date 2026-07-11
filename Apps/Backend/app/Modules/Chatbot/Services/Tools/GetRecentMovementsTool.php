<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Arr;

/**
 * `get_recent_movements` — the latest ledger entries, store-wide or for one
 * product. Each row is slimmed to what the model needs to narrate activity.
 */
final class GetRecentMovementsTool
{
    private const ROW_KEYS = [
        'product_id', 'product_name', 'type', 'quantity',
        'quantity_after', 'reason', 'created_at',
    ];

    public function __construct(
        private readonly StockServiceInterface $stock,
        private readonly int $defaultLimit,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_recent_movements',
            description: 'The most recent stock-ledger entries (sales, restocks, adjustments), newest first — store-wide, or for one product when product_id is given. Use for "what happened recently?", "show the last sales of product X", "when did we last restock?". This is only a small sample of the latest rows — NEVER use it to rank products or total sales over a period (use get_top_products / get_sales_trends for that).',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
            ],
            handler: function (array $args): array {
                $limit = max(1, min(50, (int) ($args['limit'] ?? $this->defaultLimit)));

                if (isset($args['product_id'])) {
                    $page = $this->stock->history((int) $args['product_id'], perPage: $limit);
                    $rows = $page->items;
                    $total = $page->total;
                } else {
                    $rows = $this->stock->recent($limit);
                    $total = count($rows);
                }

                $movements = array_map(
                    static fn ($dto): array => Arr::only(is_array($dto) ? $dto : $dto->toArray(), self::ROW_KEYS),
                    $rows,
                );

                return [
                    'movements' => array_values($movements),
                    'total' => $total,
                    'truncated' => $total > count($movements),
                ];
            },
        );
    }
}
