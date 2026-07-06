<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotency map: one local product per Shopify variant.
 *
 * @property int $id
 * @property int $product_id
 * @property string $shopify_product_id
 * @property string $shopify_variant_id
 * @property string|null $shopify_inventory_item_id
 */
#[Fillable(['product_id', 'shopify_product_id', 'shopify_variant_id', 'shopify_inventory_item_id'])]
class ShopifyProductMap extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
