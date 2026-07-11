<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use Illuminate\Support\Arr;

/**
 * `get_product_recommendation` — one product's full verdict: what to do,
 * how many to order, by when, and why.
 */
final class GetProductRecommendationTool
{
    private const KEYS = [
        'product_id', 'sku', 'name', 'type', 'reasoning', 'current_stock',
        'sales_velocity', 'days_of_stock_left', 'lead_time_days', 'needs_reorder',
        'suggested_reorder_qty', 'reorder_by_date', 'is_urgent', 'is_overstocked',
        'cash_tied_up', 'stockout_risk', 'projected_stockout_date',
        'demand_trend_pct', 'projected_units_30d', 'projected_revenue_30d',
        'forecast_source', 'model_used',
    ];

    public function __construct(
        private readonly IntelligenceServiceInterface $intelligence,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_product_recommendation',
            description: 'The full recommendation for ONE product by its numeric id: verdict (reorder/overstock/dead_stock/healthy), suggested order quantity and deadline, stockout risk and projected stockout date, demand trend, cash tied up, and the reasoning. Resolve names to ids with find_product first.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
                'required' => ['product_id'],
            ],
            handler: fn (array $args): array => Arr::only(
                $this->intelligence->forProduct((int) $args['product_id'])->toArray(),
                self::KEYS,
            ),
        );
    }
}
