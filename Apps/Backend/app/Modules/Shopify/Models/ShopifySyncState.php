<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row sync watermark. `orders_synced_until` is null until the initial
 * history backfill has run; afterwards it holds the created_at of the newest
 * imported order so incremental runs only fetch what's new.
 *
 * @property int $id
 * @property CarbonImmutable|null $orders_synced_until
 * @property CarbonImmutable|null $products_synced_at
 */
#[Fillable(['orders_synced_until', 'products_synced_at'])]
class ShopifySyncState extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orders_synced_until' => 'immutable_datetime',
            'products_synced_at' => 'immutable_datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
