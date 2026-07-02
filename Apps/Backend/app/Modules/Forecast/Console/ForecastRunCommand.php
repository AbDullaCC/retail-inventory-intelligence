<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Console;

use App\Modules\Forecast\Exceptions\ForecastServiceUnavailableException;
use App\Modules\Forecast\Services\Contracts\ForecastRunnerInterface;
use Illuminate\Console\Command;

/**
 * Refreshes every product's stored forecast from the Python sidecar. Runs
 * daily via the scheduler; safe to run manually any time. If the sidecar is
 * down the command fails with instructions instead of a stack trace —
 * Intelligence keeps working on its window-average fallback.
 */
final class ForecastRunCommand extends Command
{
    protected $signature = 'forecast:run {--products=* : Only these product ids}';

    protected $description = 'Fetch demand forecasts from the sidecar and store them per product.';

    public function handle(ForecastRunnerInterface $runner): int
    {
        $ids = array_map('intval', (array) $this->option('products'));

        $started = microtime(true);

        try {
            $summary = $runner->run($ids === [] ? null : $ids);
        } catch (ForecastServiceUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Forecasted %d product%s (%d skipped — no sales history) in %.1fs.',
            $summary['forecasted'],
            $summary['forecasted'] === 1 ? '' : 's',
            $summary['skipped'],
            microtime(true) - $started,
        ));

        ksort($summary['models']);
        foreach ($summary['models'] as $model => $count) {
            $this->components->twoColumnDetail($model, (string) $count);
        }

        return self::SUCCESS;
    }
}
