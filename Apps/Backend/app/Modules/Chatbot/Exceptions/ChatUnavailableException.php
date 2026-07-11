<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;
use Throwable;

/**
 * The LLM provider is unreachable, misconfigured, or returned an error.
 * Rendered as HTTP 503 with the provider's own message so the user sees the
 * real cause ("API key not valid", "model is overloaded", …) instead of a
 * generic string.
 */
final class ChatUnavailableException extends DomainException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 503, $previous);
    }

    public static function notConfigured(): self
    {
        return new self(
            'The AI assistant is not configured yet — set GEMINI_API_KEY in the backend .env '
            .'(free key at https://aistudio.google.com/apikey).',
        );
    }
}
