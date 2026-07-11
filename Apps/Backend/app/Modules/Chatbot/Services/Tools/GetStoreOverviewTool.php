<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use Illuminate\Support\Arr;

/**
 * `get_store_overview` — the dashboard headline numbers: catalogue size,
 * stock units/value, and how many products are low or out of stock.
 */
final class GetStoreOverviewTool
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_store_overview',
            description: 'Store-wide snapshot: total/active products, categories, total stock units and stock value, low-stock and out-of-stock counts, plus the top low-stock products. Use for "how is my store doing?", "how much is my inventory worth?", "what is low on stock?".',
            parameters: [],
            handler: function (array $args): array {
                unset($args);

                $summary = $this->dashboard->summary()->toArray();

                $lowStock = array_map(
                    static fn (array $p): array => Arr::only($p, ['sku', 'name', 'quantity', 'reorder_level']),
                    array_map(
                        static fn ($p): array => is_array($p) ? $p : $p->toArray(),
                        array_slice($summary['low_stock_products'] ?? [], 0, 5),
                    ),
                );

                return [
                    ...Arr::only($summary, [
                        'total_products',
                        'active_products',
                        'total_categories',
                        'total_stock_units',
                        'total_stock_value',
                        'low_stock_count',
                        'out_of_stock_count',
                    ]),
                    'top_low_stock_products' => $lowStock,
                ];
            },
        );
    }
}
