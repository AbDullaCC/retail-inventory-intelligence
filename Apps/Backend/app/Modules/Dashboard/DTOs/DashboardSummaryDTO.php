<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\DTOs;

use App\Modules\Product\DTOs\ProductDTO;
use App\Modules\Shared\DTOs\BaseData;
use App\Modules\Stock\DTOs\StockMovementDTO;

final class DashboardSummaryDTO extends BaseData
{
    /**
     * @param  array<int, ProductDTO>  $lowStockProducts
     * @param  array<int, StockMovementDTO>  $recentMovements
     */
    public function __construct(
        public readonly int $totalProducts,
        public readonly int $activeProducts,
        public readonly int $totalCategories,
        public readonly int $lowStockCount,
        public readonly int $outOfStockCount,
        public readonly int $totalStockUnits,
        public readonly float $totalStockValue,
        public readonly array $lowStockProducts,
        public readonly array $recentMovements,
    ) {}

    public function toArray(): array
    {
        return [
            'total_products' => $this->totalProducts,
            'active_products' => $this->activeProducts,
            'total_categories' => $this->totalCategories,
            'low_stock_count' => $this->lowStockCount,
            'out_of_stock_count' => $this->outOfStockCount,
            'total_stock_units' => $this->totalStockUnits,
            'total_stock_value' => $this->totalStockValue,
            'low_stock_products' => array_map(static fn ($p) => $p->toArray(), $this->lowStockProducts),
            'recent_movements' => array_map(static fn ($m) => $m->toArray(), $this->recentMovements),
        ];
    }
}
