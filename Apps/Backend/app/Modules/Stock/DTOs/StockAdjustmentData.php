<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

use App\Modules\Stock\Enums\StockMovementType;

/**
 * Input DTO describing a requested stock change.
 */
final class StockAdjustmentData
{
    public function __construct(
        public readonly StockMovementType $type,
        public readonly int $quantity,
        public readonly ?string $reason = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: StockMovementType::from((string) $data['type']),
            quantity: (int) $data['quantity'],
            reason: isset($data['reason']) && $data['reason'] !== '' ? (string) $data['reason'] : null,
        );
    }
}
