<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services;

use App\Modules\Forecast\Exceptions\ForecastServiceUnavailableException;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Forecast\Services\Contracts\ForecastRunnerInterface;
use App\Modules\Intelligence\Support\ReorderConfig;
use App\Modules\Product\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The write side of forecasting: builds daily demand series from the ledger,
 * sends them to the Python sidecar in chunks and replaces each product's
 * stored forecast. Products without sales history are skipped — Intelligence
 * falls back to its window-average formula for them.
 */
final class ForecastRunner implements ForecastRunnerInterface
{
    public function __construct(
        private readonly DemandSeriesBuilder $series,
        private readonly ReorderConfig $config,
    ) {}

    public function run(?array $productIds = null): array
    {
        $settings = config('services.forecast');
        $today = Carbon::now()->toDateTimeImmutable();

        $this->assertHealthy($settings);

        $series = $this->series->seriesByProduct($today, (int) $settings['history_days'], $productIds);

        $models = [];
        $forecasted = 0;
        foreach (array_chunk($series, (int) $settings['chunk_size'], preserve_keys: true) as $chunk) {
            $results = $this->requestForecasts($chunk, $settings);

            DB::transaction(function () use ($results, $chunk, &$models, &$forecasted): void {
                foreach ($results['results'] as $result) {
                    $productId = (int) $result['product_id'];
                    $this->store($productId, $result, $results['generated_at'], $chunk[$productId] ?? []);
                    $models[$result['model_used']] = ($models[$result['model_used']] ?? 0) + 1;
                    $forecasted++;
                }
            });
        }

        $totalProducts = $productIds === null
            ? Product::query()->count()
            : count($productIds);

        return [
            'forecasted' => $forecasted,
            'skipped' => max(0, $totalProducts - $forecasted),
            'models' => $models,
        ];
    }

    /**
     * @param  array<int, list<array{date: string, qty: int}>>  $chunk
     * @param  array<string, mixed>  $settings
     * @return array{generated_at: string, results: list<array<string, mixed>>}
     */
    private function requestForecasts(array $chunk, array $settings): array
    {
        $payload = [
            'horizon_days' => (int) $settings['horizon_days'],
            'lead_time_days' => $this->config->defaultLeadTimeDays,
            'levels' => [90],
            'series' => array_map(
                static fn (int $productId): array => ['product_id' => $productId, 'history' => $chunk[$productId]],
                array_keys($chunk),
            ),
        ];

        try {
            return Http::baseUrl((string) $settings['url'])
                ->timeout((int) $settings['timeout'])
                ->acceptJson()
                ->post('/forecast', $payload)
                ->throw()
                ->json();
        } catch (ConnectionException $e) {
            throw ForecastServiceUnavailableException::at((string) $settings['url'], $e);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array{date: string, qty: int}>  $series
     */
    private function store(int $productId, array $result, string $generatedAt, array $series): void
    {
        $means = array_map(static fn (array $point): float => (float) $point['mean'], $result['forecast']);
        $leadPlusCoverage = $this->config->defaultLeadTimeDays + $this->config->coveragePeriodDays;

        $actualsLast28 = 0.0;
        foreach (array_slice($series, -28) as $point) {
            $actualsLast28 += $point['qty'];
        }

        ProductForecast::query()->updateOrCreate(
            ['product_id' => $productId],
            [
                'generated_at' => Carbon::parse($generatedAt),
                'horizon_days' => count($result['forecast']),
                'history_days' => (int) $result['history_days'],
                'lead_time_days' => $this->config->defaultLeadTimeDays,
                'model_used' => (string) $result['model_used'],
                'expected_daily_demand' => (float) $result['expected_daily_demand'],
                'demand_over_lead_time' => (float) $result['demand_over_lead_time'],
                'p90_demand_over_lead_time' => $result['p90_demand_over_lead_time'] !== null
                    ? (float) $result['p90_demand_over_lead_time']
                    : null,
                // The coverage knob is Laravel's, so this aggregate is computed here.
                'demand_lead_plus_coverage' => array_sum(array_slice($means, 0, $leadPlusCoverage)),
                'actuals_last_28d' => $actualsLast28,
                'daily_forecast' => $result['forecast'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function assertHealthy(array $settings): void
    {
        try {
            $healthy = Http::baseUrl((string) $settings['url'])
                ->timeout(5)
                ->get('/health')
                ->successful();
        } catch (ConnectionException $e) {
            throw ForecastServiceUnavailableException::at((string) $settings['url'], $e);
        }

        if (! $healthy) {
            throw ForecastServiceUnavailableException::at((string) $settings['url']);
        }
    }
}
