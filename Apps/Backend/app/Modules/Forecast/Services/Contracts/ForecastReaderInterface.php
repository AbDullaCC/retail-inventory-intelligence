<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services\Contracts;

use App\Modules\Forecast\DTOs\ForecastProjectionDTO;
use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Forecast\DTOs\ForecastSummaryDTO;
use App\Modules\Forecast\DTOs\ProductForecastDTO;
use DateTimeImmutable;

interface ForecastReaderInterface
{
    /**
     * Store-wide aggregation of every fresh forecast (daily demand curve,
     * projected units/revenue, model mix).
     */
    public function summary(DateTimeImmutable $now): ForecastSummaryDTO;

    /**
     * Demand projection over the next $days (clamped to 1–30; day 1 = today),
     * store-wide or for one product: units, revenue, top revenue drivers, and
     * products projected to stock out inside the window. Curves are padded at
     * the flat average beyond the stored horizon, matching summary().
     */
    public function projection(int $days, DateTimeImmutable $now, ?int $productId = null): ForecastProjectionDTO;

    /**
     * Fresh (non-stale) snapshots for every forecasted product, keyed by
     * product id. Staleness policy lives here and nowhere else.
     *
     * @return array<int, ForecastSnapshot>
     */
    public function latestSnapshots(DateTimeImmutable $now): array;

    public function snapshotFor(int $productId, DateTimeImmutable $now): ?ForecastSnapshot;

    /**
     * Chart payload for a product: recent daily actuals plus the stored
     * forecast horizon (empty forecast when none exists or it is stale).
     */
    public function chartFor(int $productId, DateTimeImmutable $now): ProductForecastDTO;
}
