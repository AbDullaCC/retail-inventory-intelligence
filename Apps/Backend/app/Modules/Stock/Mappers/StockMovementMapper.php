<?php

declare(strict_types=1);

namespace App\Modules\Stock\Mappers;

use App\Modules\Stock\DTOs\StockMovementDTO;
use App\Modules\Stock\Models\StockMovement;

final class StockMovementMapper
{
    public function toDTO(StockMovement $movement): StockMovementDTO
    {
        $type = $movement->type;

        $productName = $movement->relationLoaded('product') && $movement->product !== null
            ? $movement->product->name
            : null;

        $userName = $movement->relationLoaded('user') && $movement->user !== null
            ? $movement->user->name
            : null;

        return new StockMovementDTO(
            id: $movement->id,
            productId: (int) $movement->product_id,
            productName: $productName,
            type: $type->value,
            typeLabel: $type->label(),
            quantity: (int) $movement->quantity,
            quantityBefore: (int) $movement->quantity_before,
            quantityAfter: (int) $movement->quantity_after,
            change: (int) $movement->quantity_after - (int) $movement->quantity_before,
            reason: $movement->reason,
            userId: $movement->user_id !== null ? (int) $movement->user_id : null,
            userName: $userName,
            createdAt: $movement->created_at?->toIso8601String(),
        );
    }

    /**
     * @param  iterable<StockMovement>  $movements
     * @return array<int, StockMovementDTO>
     */
    public function toDTOCollection(iterable $movements): array
    {
        $dtos = [];

        foreach ($movements as $movement) {
            $dtos[] = $this->toDTO($movement);
        }

        return $dtos;
    }
}
