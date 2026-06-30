<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Intelligence\Support\ReorderConfig;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a demo retail catalogue.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@retail.test'],
            ['name' => 'Demo Manager', 'password' => 'password'],
        );

        /** @var StockServiceInterface $stock */
        $stock = app(StockServiceInterface::class);

        foreach ($this->catalogue() as $categoryName => $rows) {
            $category = Category::query()->firstOrCreate(
                ['name' => $categoryName],
                ['description' => $categoryName.' product line.'],
            );

            foreach ($rows as $row) {
                $product = Product::query()->firstOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'category_id' => $category->id,
                        'name' => $row['name'],
                        'description' => $row['description'] ?? null,
                        'price' => $row['price'],
                        'cost' => $row['cost'],
                        'reorder_level' => $row['reorder'],
                        'is_active' => $row['active'] ?? true,
                        'quantity' => 0,
                    ],
                );

                if (! $product->wasRecentlyCreated) {
                    continue;
                }

                $this->recordHistory($stock, $product, (int) $row['opening'], (int) ($row['sold'] ?? 0), $user->id);
            }
        }
    }

    /**
     * Build a realistic movement history: an opening restock just outside the
     * velocity window, then daily customer sales spread across it. Spreading the
     * sales (rather than one lump movement at seed time) is what lets the
     * Intelligence module derive a meaningful per-day sales velocity.
     */
    private function recordHistory(StockServiceInterface $stock, Product $product, int $opening, int $sold, int $userId): void
    {
        $window = ReorderConfig::VELOCITY_WINDOW_DAYS;

        if ($opening > 0) {
            $dto = $stock->adjust(
                $product->id,
                new StockAdjustmentData(StockMovementType::In, $opening, 'Opening stock'),
                $userId,
            );
            $this->backdate($dto->id, Carbon::now()->subDays($window + 2));
        }

        if ($sold <= 0) {
            return;
        }

        // Distribute `sold` units evenly over the window (extra units land on the
        // most recent days), so average velocity works out to sold / window.
        $base = intdiv($sold, $window);
        $remainder = $sold % $window;

        // Oldest day first so the ledger's quantity_before/after stays monotonic.
        for ($offset = $window - 1; $offset >= 0; $offset--) {
            $qty = $base + ($offset < $remainder ? 1 : 0);
            if ($qty <= 0) {
                continue;
            }

            $dto = $stock->adjust(
                $product->id,
                new StockAdjustmentData(StockMovementType::Out, $qty, 'Customer sale'),
                $userId,
            );
            $this->backdate($dto->id, Carbon::now()->subDays($offset));
        }
    }

    private function backdate(int $movementId, Carbon $when): void
    {
        StockMovement::query()->whereKey($movementId)->update([
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function catalogue(): array
    {
        return [
            'Beverages' => [
                ['sku' => 'BEV-001', 'name' => 'Spring Water 500ml', 'price' => 0.90, 'cost' => 0.40, 'reorder' => 50, 'opening' => 240, 'sold' => 60, 'description' => 'Still mineral water, 500ml bottle.'],
                ['sku' => 'BEV-002', 'name' => 'Cola Can 330ml', 'price' => 1.20, 'cost' => 0.55, 'reorder' => 80, 'opening' => 300, 'sold' => 250, 'description' => 'Classic cola, 330ml can.'],
                ['sku' => 'BEV-003', 'name' => 'Orange Juice 1L', 'price' => 2.75, 'cost' => 1.40, 'reorder' => 30, 'opening' => 90, 'sold' => 18],
                ['sku' => 'BEV-004', 'name' => 'Ground Coffee 250g', 'price' => 5.50, 'cost' => 3.10, 'reorder' => 20, 'opening' => 40, 'sold' => 9],
            ],
            'Snacks' => [
                ['sku' => 'SNK-001', 'name' => 'Potato Chips 150g', 'price' => 1.80, 'cost' => 0.85, 'reorder' => 40, 'opening' => 120, 'sold' => 35],
                ['sku' => 'SNK-002', 'name' => 'Chocolate Bar 100g', 'price' => 1.50, 'cost' => 0.70, 'reorder' => 60, 'opening' => 200, 'sold' => 175],
                ['sku' => 'SNK-003', 'name' => 'Mixed Nuts 200g', 'price' => 3.20, 'cost' => 1.90, 'reorder' => 25, 'opening' => 50, 'sold' => 12],
            ],
            'Household' => [
                ['sku' => 'HOU-001', 'name' => 'Dish Soap 750ml', 'price' => 2.40, 'cost' => 1.10, 'reorder' => 30, 'opening' => 80, 'sold' => 20],
                ['sku' => 'HOU-002', 'name' => 'Paper Towels 6-pack', 'price' => 4.90, 'cost' => 2.60, 'reorder' => 25, 'opening' => 60, 'sold' => 55],
                ['sku' => 'HOU-003', 'name' => 'Laundry Detergent 2L', 'price' => 8.50, 'cost' => 5.00, 'reorder' => 15, 'opening' => 30, 'sold' => 30],
            ],
            'Electronics' => [
                ['sku' => 'ELE-001', 'name' => 'AA Batteries 4-pack', 'price' => 3.99, 'cost' => 1.80, 'reorder' => 40, 'opening' => 100, 'sold' => 30],
                ['sku' => 'ELE-002', 'name' => 'USB-C Cable 1m', 'price' => 6.99, 'cost' => 2.50, 'reorder' => 20, 'opening' => 45, 'sold' => 15],
                ['sku' => 'ELE-003', 'name' => 'LED Bulb 9W', 'price' => 4.25, 'cost' => 1.95, 'reorder' => 30, 'opening' => 70, 'sold' => 25],
                ['sku' => 'ELE-004', 'name' => 'Wireless Mouse', 'price' => 14.99, 'cost' => 7.50, 'reorder' => 10, 'opening' => 20, 'sold' => 4, 'active' => false],
            ],
            'Personal Care' => [
                ['sku' => 'PER-001', 'name' => 'Toothpaste 100ml', 'price' => 2.10, 'cost' => 0.95, 'reorder' => 35, 'opening' => 90, 'sold' => 22],
                ['sku' => 'PER-002', 'name' => 'Shampoo 400ml', 'price' => 4.60, 'cost' => 2.30, 'reorder' => 25, 'opening' => 55, 'sold' => 14],
                ['sku' => 'PER-003', 'name' => 'Bar Soap 3-pack', 'price' => 2.80, 'cost' => 1.20, 'reorder' => 30, 'opening' => 75, 'sold' => 60],
            ],
            // Slow movers — deliberately overstocked / dormant so the intelligence
            // layer surfaces overstock and "no recent sales" recommendations.
            'Seasonal' => [
                ['sku' => 'SEA-001', 'name' => 'Holiday Gift Box', 'price' => 12.00, 'cost' => 6.00, 'reorder' => 20, 'opening' => 200, 'sold' => 14, 'description' => 'Boxed seasonal gift set — slow off-season.'],
                ['sku' => 'SEA-002', 'name' => 'Beach Umbrella', 'price' => 18.00, 'cost' => 9.00, 'reorder' => 10, 'opening' => 120, 'sold' => 7, 'description' => 'Large parasol — out of season.'],
                ['sku' => 'SEA-003', 'name' => 'Display Stand', 'price' => 7.50, 'cost' => 5.00, 'reorder' => 5, 'opening' => 40, 'sold' => 0, 'description' => 'Promotional display unit — no recent sales.'],
            ],
        ];
    }
}
