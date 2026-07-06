<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;
use Throwable;

final class ShopifyUnavailableException extends DomainException
{
    public static function at(string $domain, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Shopify store %s is unreachable — check SHOPIFY_SHOP_DOMAIN / network connectivity.', $domain),
            503,
            $previous,
        );
    }

    public static function notConfigured(): self
    {
        return new self(
            'Shopify is not configured. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN in .env '
            .'(create a custom app in your Shopify admin under Settings → Apps → Develop apps, '
            .'grant read_products, read_inventory and read_orders scopes, and install it to get an Admin API token).',
            422,
        );
    }
}
