<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * `get_product_forecast` — one product's chart payload: 90 days of daily
 * actuals plus the stored forecast horizon (with p90 bands). The forecast
 * array is empty when none exists or it is stale — returned as-is.
 */
final class GetProductForecastTool
{
    public function __construct(
        private readonly ForecastReaderInterface $forecasts,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_product_forecast',
            description: 'One product\'s demand history and forecast: 90 days of daily actual sales plus the stored forecast horizon with p90 bands. The forecast array is empty when forecasts are stale or absent — tell the user to run `php artisan forecast:run`. Use for "what\'s the forecast for product X?" or "will product X stock out?".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
                'required' => ['product_id'],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                try {
                    return $this->forecasts->chartFor((int) $args['product_id'], Carbon::now()->toDateTimeImmutable())->toArray();
                } catch (ModelNotFoundException) {
                    return ['error' => 'product not found'];
                }
            },
        );
    }
}
