<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\DTOs\DashboardSummaryDTO;
use App\Modules\Dashboard\DTOs\DashboardTrendsDTO;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Product\Mappers\ProductMapper;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Carbon;
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
    ) {}

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

    public function trends(int $days, ?int $productId = null): DashboardTrendsDTO
    {
        $today = Carbon::now()->startOfDay();
        $start = $today->copy()->subDays($days - 1);

        $rows = StockMovement::query()
            ->where('created_at', '>=', $start)
            ->when($productId !== null, static fn ($query) => $query->where('product_id', $productId))
            ->groupByRaw('DATE(created_at)')
            ->selectRaw(sprintf(
                "DATE(created_at) as day,
                 SUM(CASE WHEN type = '%s' THEN quantity ELSE 0 END) as units_in,
                 SUM(CASE WHEN type = '%s' THEN quantity ELSE 0 END) as units_out,
                 COUNT(*) as movements",
                StockMovementType::In->value,
                StockMovementType::Out->value,
            ))
            ->get()
            ->keyBy('day');

        // Zero-fill the calendar in PHP so the frontend never gap-fills.
        $series = [];
        for ($day = $start->copy(); $day <= $today; $day->addDay()) {
            $key = $day->format('Y-m-d');
            $row = $rows->get($key);
            $series[] = [
                'date' => $key,
                'units_in' => (int) ($row->units_in ?? 0),
                'units_out' => (int) ($row->units_out ?? 0),
                'movements' => (int) ($row->movements ?? 0),
            ];
        }

        return new DashboardTrendsDTO(
            days: $days,
            series: $series,
            categoryValues: $productId === null ? $this->categoryValues() : [],
        );
    }

    /**
     * @return list<array{category_id: int, category_name: string, stock_value: float, units: int}>
     */
    private function categoryValues(): array
    {
        return Product::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(products.price * products.quantity) as stock_value, SUM(products.quantity) as units')
            ->orderByDesc('stock_value')
            ->get()
            ->map(static fn ($row): array => [
                'category_id' => (int) $row->category_id,
                'category_name' => (string) $row->category_name,
                'stock_value' => round((float) $row->stock_value, 2),
                'units' => (int) $row->units,
            ])
            ->all();
    }
}
