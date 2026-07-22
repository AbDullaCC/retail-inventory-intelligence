<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services;

use App\Modules\Forecast\DTOs\ForecastProjectionDTO;
use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Forecast\DTOs\ForecastSummaryDTO;
use App\Modules\Forecast\DTOs\ProductForecastDTO;
use App\Modules\Forecast\Mappers\ForecastMapper;
use App\Modules\Forecast\Models\ProductForecast;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Product\Models\Product;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read side of forecasting. Staleness lives here and nowhere else: a forecast
 * older than the configured window is treated as absent, which makes every
 * consumer (Intelligence, charts) fall back gracefully when forecast:run
 * hasn't happened recently.
 */
final class ForecastReader implements ForecastReaderInterface
{
    private const CHART_HISTORY_DAYS = 90;

    public function __construct(
        private readonly ForecastMapper $mapper,
        private readonly DemandSeriesBuilder $series,
    ) {}

    public function summary(DateTimeImmutable $now): ForecastSummaryDTO
    {
        $forecasts = $this->freshQuery($now)->with('product')->get();

        $modelMix = [];
        $units30d = 0.0;
        $revenue30d = 0.0;
        $daily = [];
        $latest = null;

        foreach ($forecasts as $forecast) {
            $modelMix[$forecast->model_used] = ($modelMix[$forecast->model_used] ?? 0) + 1;

            // Sum the first 30 days of the actual curve (padding beyond the
            // horizon with the flat average) — keeps this KPI identical to the
            // Intelligence layer's curve-based 30-day projection.
            $means = array_map(static fn (array $point): float => (float) $point['mean'], $forecast->daily_forecast);
            $next30 = (float) array_sum(array_slice($means, 0, 30))
                + max(0, 30 - count($means)) * $forecast->expected_daily_demand;

            $units30d += $next30;
            $revenue30d += $next30 * (float) ($forecast->product->price ?? 0);
            $latest = $latest === null ? $forecast->generated_at : max($latest, $forecast->generated_at);

            foreach ($forecast->daily_forecast as $point) {
                $day = &$daily[$point['date']];
                $day ??= ['mean' => 0.0, 'hi_90' => 0.0];
                $day['mean'] += (float) $point['mean'];
                // Croston/TSB have no band — their mean is the best available worst case.
                $day['hi_90'] += (float) ($point['hi_90'] ?? $point['mean']);
                unset($day);
            }
        }

        ksort($daily);
        ksort($modelMix);

        return new ForecastSummaryDTO(
            forecastedCount: $forecasts->count(),
            projectedUnits30d: $units30d,
            projectedRevenue30d: $revenue30d,
            modelMix: $modelMix,
            generatedAt: $latest?->format('c'),
            daily: array_map(
                static fn (string $date): array => ['date' => $date, 'mean' => $daily[$date]['mean'], 'hi_90' => $daily[$date]['hi_90']],
                array_keys($daily),
            ),
        );
    }

    public function projection(int $days, DateTimeImmutable $now, ?int $productId = null): ForecastProjectionDTO
    {
        $days = max(1, min(30, $days));

        $query = $this->freshQuery($now)->with('product');
        if ($productId !== null) {
            $query->where('product_id', $productId);
        }
        $forecasts = $query->get();

        $units = 0.0;
        $revenue = 0.0;
        $stockouts = 0;
        $perProduct = [];

        foreach ($forecasts as $forecast) {
            $means = array_map(static fn (array $point): float => (float) $point['mean'], $forecast->daily_forecast);

            // Window demand off the actual curve, padded at the flat average
            // beyond the stored horizon — same convention as summary().
            $windowUnits = (float) array_sum(array_slice($means, 0, $days))
                + max(0, $days - count($means)) * $forecast->expected_daily_demand;

            $price = (float) ($forecast->product->price ?? 0);
            $units += $windowUnits;
            $revenue += $windowUnits * $price;

            if ($this->stocksOutWithin($days, $means, $forecast->expected_daily_demand, (int) ($forecast->product->quantity ?? 0))) {
                $stockouts++;
            }

            $perProduct[] = [
                'product_id' => (int) $forecast->product_id,
                'name' => (string) ($forecast->product->name ?? ('#'.$forecast->product_id)),
                'units' => $windowUnits,
                'revenue' => $windowUnits * $price,
            ];
        }

        usort($perProduct, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return new ForecastProjectionDTO(
            days: $days,
            fromDate: $now->format('Y-m-d'),
            toDate: $now->modify('+'.($days - 1).' days')->format('Y-m-d'),
            forecastedCount: $forecasts->count(),
            projectedUnits: $units,
            projectedRevenue: $revenue,
            projectedStockouts: $stockouts,
            topProducts: array_slice($perProduct, 0, 5),
        );
    }

    /**
     * Does cumulative expected demand reach the on-hand stock inside the
     * window? Zero-demand products never count, even at zero stock.
     *
     * @param  list<float>  $means
     */
    private function stocksOutWithin(int $days, array $means, float $flatDaily, int $stock): bool
    {
        $cumulative = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $cumulative += $means[$i] ?? $flatDaily;
            if ($cumulative > 0.0 && $cumulative >= $stock) {
                return true;
            }
        }

        return false;
    }

    public function latestSnapshots(DateTimeImmutable $now): array
    {
        $snapshots = [];
        foreach ($this->freshQuery($now)->get() as $forecast) {
            $snapshots[$forecast->product_id] = $this->mapper->toSnapshot($forecast);
        }

        return $snapshots;
    }

    public function snapshotFor(int $productId, DateTimeImmutable $now): ?ForecastSnapshot
    {
        $forecast = $this->freshQuery($now)->where('product_id', $productId)->first();

        return $forecast === null ? null : $this->mapper->toSnapshot($forecast);
    }

    public function chartFor(int $productId, DateTimeImmutable $now): ProductForecastDTO
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($productId);

        /** @var ProductForecast|null $forecast */
        $forecast = $this->freshQuery($now)->where('product_id', $productId)->first();

        return $this->mapper->toChartDTO(
            $product,
            $forecast,
            $this->series->actualsFor($productId, self::CHART_HISTORY_DAYS, $now),
        );
    }

    /**
     * @return Builder<ProductForecast>
     */
    private function freshQuery(DateTimeImmutable $now)
    {
        $staleHours = (int) config('services.forecast.stale_after_hours');

        return ProductForecast::query()
            ->where('generated_at', '>=', $now->modify("-{$staleHours} hours")->format('Y-m-d H:i:s'));
    }
}
