<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use DateTimeImmutable;

/**
 * `get_product_forecast` — one product's demand outlook, condensed for an LLM:
 * expected units for the next 7/28 days plus recent actuals, never the full
 * 90-day daily series.
 */
final class GetProductForecastTool
{
    public function __construct(
        private readonly ForecastReaderInterface $forecasts,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_product_forecast',
            description: 'Demand forecast for ONE product by numeric id: expected units over the next 7 and 28 days (with the model used), the next 7 days day-by-day, and actual units sold in the last 7/28 days for comparison. States clearly when no fresh forecast exists. Resolve names to ids with find_product first.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
                'required' => ['product_id'],
            ],
            handler: function (array $args): array {
                $chart = $this->forecasts->chartFor((int) $args['product_id'], new DateTimeImmutable);

                $history = $chart->history;
                $lastN = static function (int $days) use ($history): int {
                    $slice = array_slice($history, -$days);

                    return (int) array_sum(array_column($slice, 'qty'));
                };

                $result = [
                    'product_id' => $chart->productId,
                    'sku' => $chart->sku,
                    'name' => $chart->name,
                    'actual_units_last_7_days' => $lastN(7),
                    'actual_units_last_28_days' => $lastN(28),
                    'forecast_available' => $chart->forecast !== [],
                ];

                if ($chart->forecast === []) {
                    $result['note'] = 'No fresh forecast for this product — forecasts can be refreshed from the Integrations page.';

                    return $result;
                }

                $mean = array_column($chart->forecast, 'mean');

                return [
                    ...$result,
                    'model_used' => $chart->modelUsed,
                    'generated_at' => $chart->generatedAt,
                    'expected_units_next_7_days' => round((float) array_sum(array_slice($mean, 0, 7)), 1),
                    'expected_units_next_28_days' => round((float) array_sum($mean), 1),
                    'daily_next_7_days' => array_map(
                        static fn (array $point): array => [
                            'date' => $point['date'],
                            'expected' => round((float) $point['mean'], 1),
                        ],
                        array_slice($chart->forecast, 0, 7),
                    ),
                ];
            },
        );
    }
}
