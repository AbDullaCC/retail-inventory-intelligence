<?php

declare(strict_types=1);

namespace App\Modules\Forecast\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;
use Throwable;

final class ForecastServiceUnavailableException extends DomainException
{
    public static function at(string $url, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Forecast sidecar unreachable at %s — start it with: uvicorn app.main:app --host 127.0.0.1 --port 8100 (from Apps/Forecast).', $url),
            503,
            $previous,
        );
    }
}
