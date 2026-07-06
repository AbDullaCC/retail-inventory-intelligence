<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

use App\Modules\Shared\DTOs\BaseData;

final class StockMovementDTO extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly ?string $productName,
        public readonly string $type,
        public readonly string $typeLabel,
        public readonly int $quantity,
        public readonly int $quantityBefore,
        public readonly int $quantityAfter,
        public readonly int $change,
        public readonly ?string $reason,
        public readonly ?int $userId,
        public readonly ?string $userName,
        public readonly ?string $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'type' => $this->type,
            'type_label' => $this->typeLabel,
            'quantity' => $this->quantity,
            'quantity_before' => $this->quantityBefore,
            'quantity_after' => $this->quantityAfter,
            'change' => $this->change,
            'reason' => $this->reason,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'created_at' => $this->createdAt,
        ];
    }
}
