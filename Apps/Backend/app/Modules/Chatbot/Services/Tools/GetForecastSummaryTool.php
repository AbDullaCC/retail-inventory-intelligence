<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use Illuminate\Support\Carbon;

/**
 * `get_forecast_summary` — the store-wide forward demand view (projected
 * units/revenue over 30 days, model mix, daily demand curve). When no fresh
 * forecasts exist (stale > 48h or never run), forecasted_count is 0 and the
 * arrays are empty — passed through as data, not an error, so the model says
 * "run forecast:run" rather than inventing numbers.
 */
final class GetForecastSummaryTool
{
    public function __construct(
        private readonly ForecastReaderInterface $forecasts,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_forecast_summary',
            description: 'Store-wide demand forecast: projected units and revenue over the next 30 days, the forecasting model mix, the generated-at timestamp, and the per-day expected demand curve. Returns forecasted_count 0 when forecasts are stale or have never been generated — tell the user to run `php artisan forecast:run` in that case. Use for "what\'s my upcoming demand?" or "how much will I sell this month?".',
            parameters: ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false],
            handler: function (array $args): array {
                return $this->forecasts->summary(Carbon::now()->toDateTimeImmutable())->toArray();
            },
        );
    }
}
