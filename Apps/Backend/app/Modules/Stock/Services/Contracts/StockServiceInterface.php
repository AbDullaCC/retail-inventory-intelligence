<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services\Contracts;

use App\Modules\Shared\DTOs\PaginatedData;
use App\Modules\Stock\DTOs\StockAdjustmentData;
use App\Modules\Stock\DTOs\StockMovementDTO;

interface StockServiceInterface
{
    /**
     * Apply a stock change to a product and record the movement.
     */
    public function adjust(int $productId, StockAdjustmentData $data, ?int $userId = null): StockMovementDTO;

    /**
     * Paginated movement history for a single product.
     */
    public function history(int $productId, int $perPage = 15, int $page = 1): PaginatedData;

    /**
     * Most recent movements across all products.
     *
     * @return array<int, StockMovementDTO>
     */
    public function recent(int $limit = 10): array;
}
