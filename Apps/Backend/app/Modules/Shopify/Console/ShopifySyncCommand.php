<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Console;

use App\Modules\Shopify\Exceptions\ShopifyUnavailableException;
use App\Modules\Shopify\Services\Contracts\ShopifySyncServiceInterface;
use App\Modules\Shopify\Support\ShopifyClient;
use Illuminate\Console\Command;

/**
 * Pulls the connected Shopify store into the local catalogue and ledger.
 * The first run backfills order history (feeding the forecasting models);
 * subsequent runs are incremental. Follow a backfill with forecast:run.
 */
final class ShopifySyncCommand extends Command
{
    protected $signature = 'shopify:sync {--products-only : Sync the catalogue without orders/inventory}';

    protected $description = 'Import products, order history and inventory levels from the connected Shopify store.';

    public function handle(ShopifySyncServiceInterface $sync, ShopifyClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Shopify is not configured.');
            $this->line('  1. In your Shopify admin: Settings → Apps and sales channels → Develop apps → Create an app.');
            $this->line('  2. Grant the Admin API scopes read_products, read_inventory, read_orders (add read_all_orders for history older than 60 days).');
            $this->line('  3. Install the app and copy the Admin API access token.');
            $this->line('  4. Set SHOPIFY_SHOP_DOMAIN (your-store.myshopify.com) and SHOPIFY_ADMIN_TOKEN in .env.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $this->components->info(sprintf('Syncing from %s…', $client->domain()));

        try {
            $stats = $sync->sync(productsOnly: (bool) $this->option('products-only'));
        } catch (ShopifyUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Products (created / updated)', sprintf('%d / %d', $stats['products_created'], $stats['products_updated']));
        if ($stats['variants_skipped'] > 0) {
            $this->components->twoColumnDetail('Variants skipped (no SKU)', (string) $stats['variants_skipped']);
        }
        if (! $this->option('products-only')) {
            $this->components->twoColumnDetail(
                $stats['backfill'] ? 'Orders backfilled' : 'New orders imported',
                sprintf('%d (%d lines)', $stats['orders_imported'], $stats['order_lines_imported']),
            );
            if ($stats['order_lines_conflicted'] > 0) {
                $this->components->twoColumnDetail('Lines skipped (conflicts)', (string) $stats['order_lines_conflicted']);
            }
            $this->components->twoColumnDetail('Inventory adjustments', (string) $stats['inventory_adjustments']);
        }
        $this->components->twoColumnDetail('Elapsed', sprintf('%.1fs', microtime(true) - $started));

        if ($stats['backfill'] && $stats['order_lines_imported'] > 0) {
            $this->newLine();
            $this->components->info('History backfilled — run `php artisan forecast:run` to forecast on the imported sales.');
        }

        return self::SUCCESS;
    }
}
