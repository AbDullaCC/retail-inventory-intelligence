<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

/**
 * The per-user hourly message cap was hit (protects the free-tier quota).
 */
final class ChatRateLimitException extends DomainException
{
    public static function forLimit(int $perHour): self
    {
        return new self(
            sprintf('You have reached the limit of %d assistant messages per hour. Please try again later.', $perHour),
            429,
        );
    }
}
