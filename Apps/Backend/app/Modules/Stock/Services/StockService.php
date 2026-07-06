<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Product\Models\Product;
use App\Modules\Shared\DTOs\PaginatedData;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\DTOs\StockMovementDTO;
use App\Modules\Stock\Enums\StockMovementType;
use App\Modules\Stock\Exceptions\InsufficientStockException;
use App\Modules\Stock\Mappers\StockMovementMapper;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\Contracts\StockServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for inventory movements. This module is the single source of
 * truth for a product's on-hand quantity — every change is applied atomically
 * and written to the immutable stock_movements ledger.
 */
final class StockService implements StockServiceInterface
{
    public function __construct(
        private readonly StockMovementMapper $mapper,
    ) {}

    public function adjust(int $productId, StockAdjustmentData $data, ?int $userId = null): StockMovementDTO
    {
        if ($data->quantity < 0) {
            throw new DomainException('Quantity must be zero or greater.');
        }

        return DB::transaction(function () use ($productId, $data, $userId): StockMovementDTO {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            $before = (int) $product->quantity;
            $after = match ($data->type) {
                StockMovementType::In => $before + $data->quantity,
                StockMovementType::Out => $before - $data->quantity,
                StockMovementType::Adjustment => $data->quantity,
            };

            if ($after < 0) {
                throw InsufficientStockException::for($before, $data->quantity);
            }

            $product->quantity = $after;
            $product->save();

            /** @var StockMovement $movement */
            $movement = StockMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'type' => $data->type,
                'quantity' => $data->quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $data->reason,
            ]);

            $movement->setRelation('product', $product);

            return $this->mapper->toDTO($movement);
        });
    }

    public function history(int $productId, int $perPage = 15, int $page = 1): PaginatedData
    {
        // Surface a 404 if the product does not exist.
        Product::query()->findOrFail($productId);

        $paginator = StockMovement::query()
            ->where('product_id', $productId)
            ->with(['product', 'user'])
            ->latest('id')
            ->paginate(perPage: $perPage, page: $page);

        return PaginatedData::fromPaginator($paginator, fn (StockMovement $m) => $this->mapper->toDTO($m));
    }

    public function recent(int $limit = 10): array
    {
        $movements = StockMovement::query()
            ->with(['product', 'user'])
            ->latest('id')
            ->limit($limit)
            ->get();

        return $this->mapper->toDTOCollection($movements);
    }
}
