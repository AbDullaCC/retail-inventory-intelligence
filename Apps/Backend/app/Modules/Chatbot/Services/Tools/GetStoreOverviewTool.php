<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use Illuminate\Support\Arr;

/**
 * `get_store_overview` — the dashboard headline numbers: catalogue size,
 * stock units/value, and how many products need reordering or are out of
 * stock. The reorder figures come from the intelligence engine (forecast- or
 * velocity-based verdicts), not the manual reorder-level column.
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
            description: 'Store-wide snapshot: total/active products, categories, total stock units and stock value, how many products need reordering (and how many of those are urgent) and how many are out of stock, plus the top products to reorder. Use for "how is my store doing?", "how much is my inventory worth?", "what is low on stock?".',
            parameters: [],
            handler: function (array $args): array {
                unset($args);

                $summary = $this->dashboard->summary()->toArray();

                $topReorder = array_map(
                    static fn (array $r): array => Arr::only($r, [
                        'sku', 'name', 'current_stock', 'days_of_stock_left',
                        'is_urgent', 'suggested_reorder_qty',
                    ]),
                    array_map(
                        static fn ($r): array => is_array($r) ? $r : $r->toArray(),
                        array_slice($summary['reorder_products'] ?? [], 0, 5),
                    ),
                );

                return [
                    ...Arr::only($summary, [
                        'total_products',
                        'active_products',
                        'total_categories',
                        'total_stock_units',
                        'total_stock_value',
                        'reorder_count',
                        'urgent_count',
                        'out_of_stock_count',
                    ]),
                    'top_reorder_products' => $topReorder,
                ];
            },
        );
    }
}
