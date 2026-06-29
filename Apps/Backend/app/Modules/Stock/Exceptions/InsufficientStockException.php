<?php

declare(strict_types=1);

namespace App\Modules\Stock\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public static function for(int $available, int $requested): self
    {
        return new self(
            sprintf(
                'Insufficient stock: tried to remove %d unit(s) but only %d available.',
                $requested,
                $available,
            ),
            422,
        );
    }
}
