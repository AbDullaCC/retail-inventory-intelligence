<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for business-rule violations.
 *
 * Thrown from the Business Logic layer when an operation is rejected by a domain
 * rule (e.g. deleting a category that still has products). The exception carries
 * the HTTP status it should map to; rendering to JSON happens centrally in
 * bootstrap/app.php.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
