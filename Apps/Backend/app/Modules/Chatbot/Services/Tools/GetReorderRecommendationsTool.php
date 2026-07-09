<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;

/**
 * `get_reorder_recommendations` — the per-product recommendation list,
 * optionally filtered by verdict and truncated to a cap so a 250-SKU store
 * never floods the LLM context.
 */
final class GetReorderRecommendationsTool
{
    private const VERDICTS = ['reorder', 'overstock', 'healthy', 'dead_stock'];

    public function __construct(
        private readonly IntelligenceServiceInterface $intelligence,
        private readonly int $maxItems,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_reorder_recommendations',
            description: 'Per-product inventory recommendations: each product\'s verdict (reorder/overstock/healthy/dead_stock), suggested reorder qty, urgency, stockout risk, projected stockout date, demand trend, and the human-readable reasoning. Optional `verdict` filter and `limit` (default 20, max 50). Use for "what should I reorder?", "what\'s dead stock?", "show me urgent items".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'verdict' => ['type' => 'string', 'enum' => self::VERDICTS, 'description' => 'Filter to one verdict.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                ],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
                $verdict = $args['verdict'] ?? null;

                $all = $this->intelligence->recommendations()->recommendations;
                if ($verdict !== null && in_array($verdict, self::VERDICTS, true)) {
                    $all = array_values(array_filter(
                        $all,
                        static fn ($r): bool => $r->type === $verdict,
                    ));
                }

                $truncated = ToolResultTruncator::truncate(
                    array_map(static fn ($r) => $r->toArray(), $all),
                    $this->maxItems,
                );

                return [
                    'recommendations' => $truncated['items'],
                    'total' => $truncated['total'],
                    'returned' => count($truncated['items']),
                    'truncated' => $truncated['truncated'],
                    'limit_requested' => $limit,
                ];
            },
        );
    }
}
