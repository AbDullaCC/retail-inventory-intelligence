<?php

declare(strict_types=1);

namespace App\Modules\Product\Models;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Database\Factories\ProductFactory;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $cost
 * @property int $quantity
 * @property int $reorder_level
 * @property bool $is_active
 */
#[Fillable(['category_id', 'sku', 'name', 'description', 'price', 'cost', 'quantity', 'reorder_level', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'quantity' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
