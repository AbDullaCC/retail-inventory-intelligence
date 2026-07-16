<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\DTOs\DashboardSummaryDTO;
use App\Modules\Dashboard\DTOs\DashboardTrendsDTO;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates read-only KPIs across the Product, Category, Stock and
 * Intelligence modules for the dashboard landing screen.
 *
 * The restocking alert is intelligence-driven: it counts the products whose
 * verdict is `reorder` (forecast- or velocity-based), NOT the manual
 * `reorder_level` column — that field survives only as an optional per-product
 * minimum (it defaults to 0 for Shopify-synced products, so an alert built on
 * it would be dead for connected stores). Dashboard and Recommendations page
 * therefore always agree on "what needs ordering".
 */
final class DashboardService implements DashboardServiceInterface
{
    /**
     * Rows per landing-screen panel. The reorder-alert and recent-activity
     * cards sit side by side, so they share one length on purpose.
     */
    private const PANEL_ITEMS = 5;

    public function __construct(
        private readonly StockServiceInterface $stockService,
        private readonly IntelligenceServiceInterface $intelligence,
    ) {}

    public function summary(): DashboardSummaryDTO
    {
        $intel = $this->intelligence->recommendations();

        $reorder = array_values(array_filter(
            $intel->recommendations,
            static fn (RecommendationDTO $r): bool => $r->type === 'reorder',
        ));

        // Urgent first, then shortest cover — same ordering as the chatbot's
        // get_recommendations tool, so every surface shows the same top items.
        usort($reorder, static function (RecommendationDTO $a, RecommendationDTO $b): int {
            $urgency = (int) $b->isUrgent <=> (int) $a->isUrgent;
            if ($urgency !== 0) {
                return $urgency;
            }

            return ($a->daysOfStockLeft ?? PHP_FLOAT_MAX) <=> ($b->daysOfStockLeft ?? PHP_FLOAT_MAX);
        });

        return new DashboardSummaryDTO(
            totalProducts: Product::query()->count(),
            activeProducts: Product::query()->where('is_active', true)->count(),
            totalCategories: Category::query()->count(),
            reorderCount: $intel->reorderCount,
            urgentCount: count(array_filter($reorder, static fn (RecommendationDTO $r): bool => $r->isUrgent)),
            outOfStockCount: Product::query()->where('quantity', 0)->count(),
            totalStockUnits: (int) Product::query()->sum('quantity'),
            totalStockValue: round((float) Product::query()->sum(DB::raw('price * quantity')), 2),
            reorderProducts: array_slice($reorder, 0, self::PANEL_ITEMS),
            recentMovements: $this->stockService->recent(self::PANEL_ITEMS),
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

    public function topProducts(int $days, int $limit): array
    {
        // Same calendar-window convention as trends(): today plus the
        // previous $days-1 whole days.
        $start = Carbon::now()->startOfDay()->subDays($days - 1);

        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.type', StockMovementType::Out->value)
            ->where('stock_movements.created_at', '>=', $start)
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.price')
            ->selectRaw('products.id as product_id, products.sku, products.name, products.price, SUM(stock_movements.quantity) as units_sold')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'units_sold' => (int) $row->units_sold,
                'revenue' => round((float) $row->units_sold * (float) $row->price, 2),
            ])
            ->all();
    }

    public function salesByCategory(int $days): array
    {
        // Same calendar-window convention as trends() and topProducts().
        $start = Carbon::now()->startOfDay()->subDays($days - 1);

        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('stock_movements.type', StockMovementType::Out->value)
            ->where('stock_movements.created_at', '>=', $start)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(stock_movements.quantity) as units_sold, SUM(stock_movements.quantity * products.price) as revenue')
            ->orderByDesc('units_sold')
            ->get()
            ->map(static fn ($row): array => [
                'category_id' => (int) $row->category_id,
                'category_name' => (string) $row->category_name,
                'units_sold' => (int) $row->units_sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
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
