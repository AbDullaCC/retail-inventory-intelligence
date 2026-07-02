<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Models;

use App\Modules\Product\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest sidecar forecast for a product (one row per product,
 * replaced on each forecast:run).
 *
 * @property int $id
 * @property int $product_id
 * @property CarbonImmutable $generated_at
 * @property int $horizon_days
 * @property int $history_days
 * @property int $lead_time_days
 * @property string $model_used
 * @property float $expected_daily_demand
 * @property float $demand_over_lead_time
 * @property float|null $p90_demand_over_lead_time
 * @property float $demand_lead_plus_coverage
 * @property float $actuals_last_28d
 * @property array<int, array{date: string, mean: float, lo_90: float|null, hi_90: float|null}> $daily_forecast
 */
#[Fillable([
    'product_id', 'generated_at', 'horizon_days', 'history_days', 'lead_time_days',
    'model_used', 'expected_daily_demand', 'demand_over_lead_time',
    'p90_demand_over_lead_time', 'demand_lead_plus_coverage', 'actuals_last_28d',
    'daily_forecast',
])]
class ProductForecast extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'horizon_days' => 'integer',
            'history_days' => 'integer',
            'lead_time_days' => 'integer',
            'expected_daily_demand' => 'float',
            'demand_over_lead_time' => 'float',
            'p90_demand_over_lead_time' => 'float',
            'demand_lead_plus_coverage' => 'float',
            'actuals_last_28d' => 'float',
            'daily_forecast' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
