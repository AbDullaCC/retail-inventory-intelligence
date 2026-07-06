<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The connected Shopify store (single row, created via the Integrations UI).
 *
 * @property int $id
 * @property string $domain
 * @property string $token encrypted at rest
 * @property string|null $shop_name
 * @property CarbonImmutable|null $last_synced_at
 * @property array<string, mixed>|null $last_stats
 */
#[Fillable(['domain', 'token', 'shop_name', 'last_synced_at', 'last_stats'])]
class ShopifyConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_synced_at' => 'immutable_datetime',
            'last_stats' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
