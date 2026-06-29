<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\DTOs\DashboardSummaryDTO;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Product\Mappers\ProductMapper;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates read-only KPIs across the Product, Category and Stock modules for
 * the dashboard landing screen.
 */
final class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly ProductMapper $productMapper,
        private readonly StockServiceInterface $stockService,
    ) {
    }

    public function summary(): DashboardSummaryDTO
    {
        $lowStockProducts = Product::query()
            ->with('category')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('quantity')
            ->limit(5)
            ->get();

        return new DashboardSummaryDTO(
            totalProducts: Product::query()->count(),
            activeProducts: Product::query()->where('is_active', true)->count(),
            totalCategories: Category::query()->count(),
            lowStockCount: Product::query()->whereColumn('quantity', '<=', 'reorder_level')->count(),
            outOfStockCount: Product::query()->where('quantity', 0)->count(),
            totalStockUnits: (int) Product::query()->sum('quantity'),
            totalStockValue: round((float) Product::query()->sum(DB::raw('price * quantity')), 2),
            lowStockProducts: $this->productMapper->toDTOCollection($lowStockProducts),
            recentMovements: $this->stockService->recent(8),
        );
    }
}
