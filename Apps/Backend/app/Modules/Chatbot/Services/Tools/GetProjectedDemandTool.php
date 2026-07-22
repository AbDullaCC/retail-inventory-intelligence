<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use Illuminate\Support\Carbon;

/**
 * `get_projected_demand` — forward-looking units/revenue over an arbitrary
 * window (1–30 days from today), server-computed from the stored per-product
 * forecast curves. The model repeats these figures; it never derives them.
 */
final class GetProjectedDemandTool
{
    public function __construct(
        private readonly ForecastReaderInterface $forecasts,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_projected_demand',
            description: 'Forward-looking projection over the next N days (1-30, default 7), computed from the per-product demand-forecast curves: projected units and revenue for the whole store (or one product via product_id), the top products by projected revenue in the window, and how many products are projected to run out of stock inside it. Use for "how much revenue do we expect today / this week / in the next 10 days?", "what will we sell tomorrow?" — days=1 means today only. Needs fresh forecasts; returns an error when there are none.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            handler: function (array $args): array {
                $days = max(1, min(30, (int) ($args['days'] ?? 7)));
                $productId = isset($args['product_id']) ? (int) $args['product_id'] : null;

                $projection = $this->forecasts->projection($days, Carbon::now()->toDateTimeImmutable(), $productId);

                if ($projection->forecastedCount === 0) {
                    return ['error' => $productId === null
                        ? 'No fresh forecasts are stored — projections are unavailable until forecasts refresh (Integrations page).'
                        : 'This product has no fresh forecast, so no projection is available for it.'];
                }

                // Whole units / 2-decimal money: the model quotes these
                // figures verbatim, so round at the boundary (display-only).
                $result = $projection->toArray();
                $result['projected_units'] = (int) round($result['projected_units']);
                $result['projected_revenue'] = round($result['projected_revenue'], 2);
                $result['top_products'] = array_map(static fn (array $p): array => [
                    'product_id' => $p['product_id'],
                    'name' => $p['name'],
                    'units' => (int) round($p['units']),
                    'revenue' => round($p['revenue'], 2),
                ], $result['top_products']);

                return $result;
            },
        );
    }
}
