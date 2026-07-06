<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Services\Contracts;

use App\Modules\Shopify\Exceptions\ShopifyUnavailableException;

interface ShopifySyncServiceInterface
{
    /**
     * Pull products, order history and inventory levels from the connected
     * Shopify store into the local catalogue and ledger.
     *
     * The first run backfills order history (up to the configured window) as
     * backdated ledger rows — this is what feeds the forecasting models. Later
     * runs are incremental from the stored watermark.
     *
     * @return array{
     *   products_created: int, products_updated: int, variants_skipped: int,
     *   orders_imported: int, order_lines_imported: int, order_lines_conflicted: int,
     *   inventory_adjustments: int, backfill: bool
     * }
     *
     * @throws ShopifyUnavailableException
     */
    public function sync(bool $productsOnly = false): array;
}
