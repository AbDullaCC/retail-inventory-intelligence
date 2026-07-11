<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use Illuminate\Support\Arr;

/**
 * `get_recommendations` — the intelligence layer's per-product verdicts plus
 * the headline counts. Rows are slimmed to the decision-relevant fields and
 * capped so a 250-SKU catalogue never floods the model's context.
 */
final class GetRecommendationsTool
{
    private const VERDICTS = ['reorder', 'overstock', 'dead_stock', 'healthy'];

    private const ROW_KEYS = [
        'product_id', 'sku', 'name', 'type', 'reasoning', 'current_stock',
        'sales_velocity', 'days_of_stock_left', 'needs_reorder',
        'suggested_reorder_qty', 'reorder_by_date', 'is_urgent', 'cash_tied_up',
        'stockout_risk', 'projected_stockout_date', 'demand_trend_pct',
        'projected_units_30d', 'projected_revenue_30d',
    ];

    public function __construct(
        private readonly IntelligenceServiceInterface $intelligence,
        private readonly int $defaultLimit,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_recommendations',
            description: 'Inventory recommendations for the whole store: headline counts (reorder/overstock/dead stock/healthy, cash tied up) plus per-product rows — verdict, suggested reorder quantity and date, urgency, stockout risk, demand trend, sales_velocity (avg units/day — rank by this for "top sellers"), and projected_units_30d / projected_revenue_30d (expected next ~month). Sorted most-urgent first. Optional verdict filter and limit. Use for "what should I reorder?", "what is overstocked?", "top selling products", "where is my cash stuck?".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'verdict' => [
                        'type' => 'string',
                        'enum' => self::VERDICTS,
                        'description' => 'Only rows with this verdict.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 50,
                        'description' => 'Max rows to return (default '.$this->defaultLimit.').',
                    ],
                ],
            ],
            handler: function (array $args): array {
                $summary = $this->intelligence->recommendations();

                $rows = $summary->recommendations;
                $verdict = $args['verdict'] ?? null;

                if (is_string($verdict) && in_array($verdict, self::VERDICTS, true)) {
                    $rows = array_values(array_filter(
                        $rows,
                        static fn (RecommendationDTO $r): bool => $r->toArray()['type'] === $verdict,
                    ));
                }

                // Urgent first, then shortest cover, so a capped slice keeps
                // the rows that matter most.
                $mapped = array_map(static fn (RecommendationDTO $r): array => $r->toArray(), $rows);
                usort($mapped, static function (array $a, array $b): int {
                    $urgency = (int) $b['is_urgent'] <=> (int) $a['is_urgent'];
                    if ($urgency !== 0) {
                        return $urgency;
                    }

                    return ($a['days_of_stock_left'] ?? PHP_FLOAT_MAX) <=> ($b['days_of_stock_left'] ?? PHP_FLOAT_MAX);
                });

                $limit = max(1, min(50, (int) ($args['limit'] ?? $this->defaultLimit)));
                $capped = ToolResultTruncator::cap($mapped, $limit);

                $counts = Arr::only($summary->toArray(), [
                    'reorder_count', 'overstock_count', 'dead_stock_count', 'healthy_count',
                    'total_cash_tied_up', 'dead_stock_cash_recoverable', 'forecasted_count',
                    'projected_revenue_30d',
                ]);

                return [
                    ...$counts,
                    'recommendations' => array_map(
                        static fn (array $row): array => Arr::only($row, self::ROW_KEYS),
                        $capped['items'],
                    ),
                    'total' => $capped['total'],
                    'truncated' => $capped['truncated'],
                    // Server-computed over ALL matching rows (not just the
                    // returned slice) — use this instead of adding rows up.
                    'cash_tied_up_in_selection' => round(array_sum(array_column($mapped, 'cash_tied_up')), 2),
                ];
            },
        );
    }
}
