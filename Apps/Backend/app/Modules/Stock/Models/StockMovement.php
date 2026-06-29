<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger row capturing a single change to a product's on-hand stock.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $quantity_before
 * @property int $quantity_after
 * @property string|null $reason
 */
#[Fillable(['product_id', 'user_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'reason'])]
class StockMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
