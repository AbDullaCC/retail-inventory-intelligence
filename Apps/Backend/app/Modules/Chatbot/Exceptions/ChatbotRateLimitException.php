<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

/**
 * The user has exceeded their per-hour message allowance. Rendered to a 429 by
 * bootstrap/app.php. Distinct from the provider's own 429 (which becomes a 503
 * after the LlmClient retries).
 */
final class ChatbotRateLimitException extends DomainException
{
    public static function forLimit(int $perHour): self
    {
        return new self(
            sprintf('You have sent too many messages this hour (limit: %d). Please try again later.', $perHour),
            429,
        );
    }
}
