<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\DTOs;

use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Shared\DTOs\BaseData;
use App\Modules\Stock\DTOs\StockMovementDTO;

final class DashboardSummaryDTO extends BaseData
{
    /**
     * @param  array<int, RecommendationDTO>  $reorderProducts
     * @param  array<int, StockMovementDTO>  $recentMovements
     */
    public function __construct(
        public readonly int $totalProducts,
        public readonly int $activeProducts,
        public readonly int $totalCategories,
        public readonly int $reorderCount,
        public readonly int $urgentCount,
        public readonly int $outOfStockCount,
        public readonly int $totalStockUnits,
        public readonly float $totalStockValue,
        public readonly array $reorderProducts,
        public readonly array $recentMovements,
    ) {}

    public function toArray(): array
    {
        return [
            'total_products' => $this->totalProducts,
            'active_products' => $this->activeProducts,
            'total_categories' => $this->totalCategories,
            'reorder_count' => $this->reorderCount,
            'urgent_count' => $this->urgentCount,
            'out_of_stock_count' => $this->outOfStockCount,
            'total_stock_units' => $this->totalStockUnits,
            'total_stock_value' => $this->totalStockValue,
            'reorder_products' => array_map(static fn ($r) => $r->toArray(), $this->reorderProducts),
            'recent_movements' => array_map(static fn ($m) => $m->toArray(), $this->recentMovements),
        ];
    }
}
