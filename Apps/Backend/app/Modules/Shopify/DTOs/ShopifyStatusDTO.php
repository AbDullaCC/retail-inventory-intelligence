<?php

declare(strict_types=1);

namespace App\Modules\Shopify\DTOs;

use App\Modules\Shared\DTOs\BaseData;
use App\Modules\Shopify\Models\ShopifyConnection;

/**
 * Connection state for the Integrations screen. `connected` is also true for
 * env-configured (CLI) setups, in which case `source` is "env" and there is
 * no stored shop name or sync stats.
 */
final class ShopifyStatusDTO extends BaseData
{
    /**
     * @param  array<string, mixed>|null  $lastStats
     */
    public function __construct(
        public readonly bool $connected,
        public readonly ?string $source,
        public readonly ?string $domain,
        public readonly ?string $shopName,
        public readonly ?string $lastSyncedAt,
        public readonly ?array $lastStats,
    ) {}

    public static function from(?ShopifyConnection $connection, bool $envConfigured, ?string $envDomain): self
    {
        if ($connection !== null) {
            return new self(
                connected: true,
                source: 'ui',
                domain: $connection->domain,
                shopName: $connection->shop_name,
                lastSyncedAt: $connection->last_synced_at?->format('c'),
                lastStats: $connection->last_stats,
            );
        }

        return new self(
            connected: $envConfigured,
            source: $envConfigured ? 'env' : null,
            domain: $envConfigured ? $envDomain : null,
            shopName: null,
            lastSyncedAt: null,
            lastStats: null,
        );
    }

    public function toArray(): array
    {
        return [
            'connected' => $this->connected,
            'source' => $this->source,
            'domain' => $this->domain,
            'shop_name' => $this->shopName,
            'last_synced_at' => $this->lastSyncedAt,
            'last_stats' => $this->lastStats,
        ];
    }
}
