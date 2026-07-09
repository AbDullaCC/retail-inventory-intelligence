<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;

/**
 * `get_store_overview` — the headline store snapshot: dashboard KPIs plus the
 * intelligence verdict counts (reorder / overstock / healthy / dead stock) and
 * cash tied up. Deliberately excludes the per-product array so a casual "how's
 * the store?" question doesn't flood the context.
 */
final class GetStoreOverviewTool
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
        private readonly IntelligenceServiceInterface $intelligence,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_store_overview',
            description: 'Headline inventory snapshot: product/stock counts, stock value, low/out-of-stock counts, recent activity, and the reorder/overstock/healthy/dead-stock verdict counts plus cash tied up. Use this for broad "how is the store doing?" questions. Does not list individual products.',
            parameters: ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false],
            handler: function (array $args): array {
                $summary = $this->dashboard->summary();
                $recs = $this->intelligence->recommendations();

                return [
                    'kpi' => [
                        'total_products' => $summary->totalProducts,
                        'active_products' => $summary->activeProducts,
                        'total_categories' => $summary->totalCategories,
                        'low_stock_count' => $summary->lowStockCount,
                        'out_of_stock_count' => $summary->outOfStockCount,
                        'total_stock_units' => $summary->totalStockUnits,
                        'total_stock_value' => $summary->totalStockValue,
                    ],
                    'verdicts' => [
                        'reorder_count' => $recs->reorderCount,
                        'overstock_count' => $recs->overstockCount,
                        'healthy_count' => $recs->healthyCount,
                        'dead_stock_count' => $recs->deadStockCount,
                        'dead_stock_cash_recoverable' => $recs->deadStockCashRecoverable,
                        'total_cash_tied_up' => $recs->totalCashTiedUp,
                    ],
                ];
            },
        );
    }
}
