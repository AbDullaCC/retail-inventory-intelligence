<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Console;

use App\Modules\Forecast\Exceptions\ForecastServiceUnavailableException;
use App\Modules\Forecast\Services\DemandSeriesBuilder;
use App\Modules\Intelligence\Support\ReorderConfig;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Rolling-origin holdout backtest: trains the sidecar models on all history
 * except the final N days, forecasts those N days, and scores the result
 * against what actually sold — side by side with the legacy formula (the flat
 * 14-day average). Produces the "does the model actually beat the old
 * formula?" evidence table.
 *
 * Metric: WAPE (weighted absolute percentage error) = Σ|actual − forecast| /
 * Σ actual, aggregated volume-weighted per model. Lower is better.
 */
final class ForecastEvaluateCommand extends Command
{
    protected $signature = 'forecast:evaluate
        {--holdout=28 : Days held out for scoring}
        {--products=* : Only these product ids}';

    protected $description = 'Backtest the forecast models against a holdout, vs the 14-day-average baseline.';

    /** Minimum training history (days) beyond the holdout for a fair fit. */
    private const MIN_TRAIN_DAYS = 56;

    public function __construct(
        private readonly DemandSeriesBuilder $series,
        private readonly ReorderConfig $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $holdout = max(7, (int) $this->option('holdout'));
        $ids = array_map('intval', (array) $this->option('products'));
        $settings = config('services.forecast');
        $today = Carbon::now()->toDateTimeImmutable();

        $all = $this->series->seriesByProduct($today, (int) $settings['history_days'], $ids === [] ? null : $ids);

        // Split each series into train/test; skip products without enough history.
        $train = [];
        $test = [];
        foreach ($all as $productId => $series) {
            if (count($series) < $holdout + self::MIN_TRAIN_DAYS) {
                continue;
            }
            $train[$productId] = array_slice($series, 0, -$holdout);
            $test[$productId] = array_slice($series, -$holdout);
        }

        if ($train === []) {
            $this->error(sprintf('No products have the %d+ days of history needed.', $holdout + self::MIN_TRAIN_DAYS));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Backtesting %d products: train up to %s, score the final %d days.',
            count($train),
            $today->modify(sprintf('-%d days', $holdout + 1))->format('Y-m-d'),
            $holdout,
        ));

        try {
            $scores = $this->score($train, $test, $holdout, $settings);
        } catch (ForecastServiceUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($scores['models'] as $model => $s) {
            $rows[] = $this->row($model, $s);
        }
        usort($rows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $rows[] = $this->row('ALL MODELS', $scores['overall']);

        $this->table(
            ['Model', '#SKUs', 'Total-demand WAPE (model / baseline / improvement)', 'Daily WAPE (model / baseline)'],
            $rows,
        );

        $this->newLine();
        $this->line(sprintf(
            '<fg=gray>Total-demand WAPE scores the %d-day holdout total per SKU — the quantity reorder decisions actually use. '
            .'Daily WAPE scores each day; it punishes intermittent-demand models for not guessing which day a sale lands. '
            .'Baseline = the legacy flat 14-day average. %d low-demand SKUs (no holdout sales) excluded.</>',
            $holdout,
            $scores['no_demand'],
        ));
        $this->line('<fg=gray>Long-history SKUs lose their second seasonal cycle when truncated, so some MSTL products backtest as AutoETS.</>');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, list<array{date: string, qty: int}>>  $train
     * @param  array<int, list<array{date: string, qty: int}>>  $test
     * @param  array<string, mixed>  $settings
     * @return array{models: array<string, array{skus: int, model_err: float, base_err: float, actual: float}>, overall: array{skus: int, model_err: float, base_err: float, actual: float}, no_demand: int}
     */
    private function score(array $train, array $test, int $holdout, array $settings): array
    {
        $models = [];
        $overall = ['skus' => 0, 'model_err' => 0.0, 'base_err' => 0.0, 'model_total_err' => 0.0, 'base_total_err' => 0.0, 'actual' => 0.0];
        $noDemand = 0;

        $bar = $this->output->createProgressBar(count($train));

        foreach (array_chunk($train, (int) $settings['chunk_size'], preserve_keys: true) as $chunk) {
            $payload = [
                'horizon_days' => $holdout,
                'lead_time_days' => $this->config->defaultLeadTimeDays,
                'levels' => [90],
                'series' => array_map(
                    static fn (int $productId): array => ['product_id' => $productId, 'history' => $chunk[$productId]],
                    array_keys($chunk),
                ),
            ];

            try {
                $response = Http::baseUrl((string) $settings['url'])
                    ->timeout((int) $settings['timeout'])
                    ->acceptJson()
                    ->post('/forecast', $payload)
                    ->throw()
                    ->json();
            } catch (ConnectionException $e) {
                throw ForecastServiceUnavailableException::at((string) $settings['url'], $e);
            }

            foreach ($response['results'] as $result) {
                $productId = (int) $result['product_id'];
                $actuals = array_map(static fn (array $p): int => $p['qty'], $test[$productId]);
                $totalActual = (float) array_sum($actuals);

                if ($totalActual <= 0.0) {
                    $noDemand++;
                    $bar->advance();

                    continue;
                }

                // Baseline: the legacy formula — flat average of the last 14 train days.
                $baseline = array_sum(array_map(
                    static fn (array $p): int => $p['qty'],
                    array_slice($train[$productId], -$this->config->velocityWindowDays),
                )) / $this->config->velocityWindowDays;

                $modelErr = 0.0;
                $baseErr = 0.0;
                $modelTotal = 0.0;
                foreach ($actuals as $i => $actual) {
                    $mean = (float) ($result['forecast'][$i]['mean'] ?? 0.0);
                    $modelTotal += $mean;
                    $modelErr += abs($actual - $mean);
                    $baseErr += abs($actual - $baseline);
                }

                // Decision-relevant error: how far off the holdout TOTAL was.
                $modelTotalErr = abs($totalActual - $modelTotal);
                $baseTotalErr = abs($totalActual - $baseline * count($actuals));

                $model = (string) $result['model_used'];
                $models[$model] ??= ['skus' => 0, 'model_err' => 0.0, 'base_err' => 0.0, 'model_total_err' => 0.0, 'base_total_err' => 0.0, 'actual' => 0.0];
                foreach ([&$models[$model], &$overall] as &$bucket) {
                    $bucket['skus']++;
                    $bucket['model_err'] += $modelErr;
                    $bucket['base_err'] += $baseErr;
                    $bucket['model_total_err'] += $modelTotalErr;
                    $bucket['base_total_err'] += $baseTotalErr;
                    $bucket['actual'] += $totalActual;
                }
                unset($bucket);

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        return ['models' => $models, 'overall' => $overall, 'no_demand' => $noDemand];
    }

    /**
     * @param  array{skus: int, model_err: float, base_err: float, model_total_err: float, base_total_err: float, actual: float}  $s
     * @return list<string>
     */
    private function row(string $model, array $s): array
    {
        $wape = static fn (float $err): float => $s['actual'] > 0 ? $err / $s['actual'] : 0.0;

        $modelTotal = $wape($s['model_total_err']);
        $baseTotal = $wape($s['base_total_err']);
        $improvement = $baseTotal > 0 ? (1 - $modelTotal / $baseTotal) * 100 : 0.0;

        return [
            $model,
            (string) $s['skus'],
            sprintf('%.1f%% / %.1f%% / %+.1f%%', $modelTotal * 100, $baseTotal * 100, $improvement),
            sprintf('%.1f%% / %.1f%%', $wape($s['model_err']) * 100, $wape($s['base_err']) * 100),
        ];
    }
}
