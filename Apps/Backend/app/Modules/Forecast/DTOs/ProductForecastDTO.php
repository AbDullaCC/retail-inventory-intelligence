<?php

declare(strict_types=1);

namespace App\Modules\Forecast\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Chart payload for a product: recent daily sales plus the forecast horizon.
 * `forecast` is empty (and the model fields null) when no fresh forecast
 * exists — the chart can always render actuals.
 */
final class ProductForecastDTO extends BaseData
{
    /**
     * @param  list<array{date: string, qty: int}>  $history
     * @param  list<array{date: string, mean: float, lo_90: float|null, hi_90: float|null}>  $forecast
     */
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $generatedAt,
        public readonly ?string $modelUsed,
        public readonly ?int $horizonDays,
        public readonly array $history,
        public readonly array $forecast,
    ) {}

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'name' => $this->name,
            'generated_at' => $this->generatedAt,
            'model_used' => $this->modelUsed,
            'horizon_days' => $this->horizonDays,
            'history' => $this->history,
            'forecast' => $this->forecast,
        ];
    }
}
