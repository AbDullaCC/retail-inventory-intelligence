<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Services;

use App\Modules\Forecast\DTOs\ForecastSnapshot;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Intelligence\DTOs\RecommendationsSummaryDTO;
use App\Modules\Intelligence\Mappers\RecommendationMapper;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Intelligence\Support\ReorderConfig;
use App\Modules\Product\Models\Product;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Models\StockMovement;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Reads existing product + stock-movement data (no parallel store — forecasts
 * are read through the Forecast module's reader contract, mirroring the
 * Product→Stock cross-module precedent) and runs it through the pure
 * {@see ReorderCalculator} to produce reorder/overstock recommendations.
 *
 * When a fresh model forecast exists for a product it drives the demand
 * estimate; otherwise velocity falls back to the historical window average:
 * `out` movements within the velocity window — `in` (restock) and `adjustment`
 * rows are ignored. The product model has no supplier-lead-time field, so lead
 * time is always defaulted; unit cost comes from the product's `cost`, falling
 * back to the configured default when null.
 */
final class IntelligenceService implements IntelligenceServiceInterface
{
    public function __construct(
        private readonly ReorderCalculator $calculator,
        private readonly RecommendationMapper $mapper,
        private readonly ReorderConfig $config,
        private readonly ForecastReaderInterface $forecasts,
    ) {}

    public function recommendations(): RecommendationsSummaryDTO
    {
        $today = $this->today();

        $products = Product::query()->with('category')->orderBy('name')->get();
        $unitsOut = $this->unitsOutByProduct($today);
        $snapshots = $this->forecasts->latestSnapshots($today);

        $reorderCount = 0;
        $overstockCount = 0;
        $healthyCount = 0;
        $deadStockCount = 0;
        $deadStockCash = 0.0;
        $forecastedCount = 0;
        $totalCashTiedUp = 0.0;
        $projectedRevenue = 0.0;
        $recommendations = [];

        foreach ($products as $product) {
            $dto = $this->buildFor($product, $unitsOut[$product->id] ?? 0, $today, $snapshots[$product->id] ?? null);
            $recommendations[] = $dto;

            $totalCashTiedUp += $dto->cashTiedUp;
            $projectedRevenue += $dto->projectedRevenue30d ?? 0.0;
            if ($dto->forecastSource === 'model') {
                $forecastedCount++;
            }
            if ($dto->type === 'reorder') {
                $reorderCount++;
            } elseif ($dto->type === 'overstock') {
                $overstockCount++;
            } elseif ($dto->type === 'dead_stock') {
                $deadStockCount++;
                $deadStockCash += $dto->cashTiedUp;
            } else {
                $healthyCount++;
            }
        }

        return new RecommendationsSummaryDTO(
            reorderCount: $reorderCount,
            overstockCount: $overstockCount,
            healthyCount: $healthyCount,
            totalCashTiedUp: $totalCashTiedUp,
            velocityWindowDays: $this->config->velocityWindowDays,
            defaultLeadTimeDays: $this->config->defaultLeadTimeDays,
            generatedAt: $today->format('c'),
            recommendations: $recommendations,
            deadStockCount: $deadStockCount,
            deadStockCashRecoverable: $deadStockCash,
            forecastedCount: $forecastedCount,
            projectedRevenue30d: $forecastedCount > 0 ? $projectedRevenue : null,
        );
    }

    public function forProduct(int $productId): RecommendationDTO
    {
        $today = $this->today();

        /** @var Product $product */
        $product = Product::query()->with('category')->findOrFail($productId);

        $unitsOut = (int) StockMovement::query()
            ->where('product_id', $productId)
            ->where('type', StockMovementType::Out->value)
            ->where('created_at', '>=', $this->windowStart($today))
            ->sum('quantity');

        return $this->buildFor($product, $unitsOut, $today, $this->forecasts->snapshotFor($productId, $today));
    }

    private function buildFor(Product $product, int $unitsOut, DateTimeImmutable $today, ?ForecastSnapshot $forecast): RecommendationDTO
    {
        $unitCostIsDefault = $product->cost === null;
        $unitCost = $unitCostIsDefault ? $this->config->defaultUnitCost : (float) $product->cost;

        // No lead-time column exists on products, so it is always the default.
        $leadTimeDays = $this->config->defaultLeadTimeDays;

        $metrics = $this->calculator->analyze(
            currentStock: (int) $product->quantity,
            unitsOutInWindow: $unitsOut,
            leadTimeDays: $leadTimeDays,
            unitCost: $unitCost,
            today: $today,
            config: $this->config,
            forecast: $forecast,
            unitPrice: (float) $product->price,
        );

        return $this->mapper->toDTO($product, $metrics, leadTimeIsDefault: true, unitCostIsDefault: $unitCostIsDefault);
    }

    /**
     * Total `out` units per product within the velocity window, keyed by product id.
     *
     * @return array<int, int>
     */
    private function unitsOutByProduct(DateTimeImmutable $today): array
    {
        return StockMovement::query()
            ->where('type', StockMovementType::Out->value)
            ->where('created_at', '>=', $this->windowStart($today))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as units_out')
            ->pluck('units_out', 'product_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
    }

    private function windowStart(DateTimeImmutable $today): DateTimeImmutable
    {
        return $today->modify("-{$this->config->velocityWindowDays} days");
    }

    private function today(): DateTimeImmutable
    {
        // Carbon::now() honours Carbon::setTestNow() in tests; converted to an
        // immutable so the calculator stays free of framework date state.
        return Carbon::now()->toDateTimeImmutable();
    }
}
