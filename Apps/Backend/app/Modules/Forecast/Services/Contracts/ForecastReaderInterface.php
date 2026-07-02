<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Services\Contracts;

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
