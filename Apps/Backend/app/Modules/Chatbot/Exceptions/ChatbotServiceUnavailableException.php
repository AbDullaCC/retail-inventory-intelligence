<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;
use Throwable;

/**
 * The LLM provider could not be reached or returned a server error after
 * retries. Rendered to a 503 by bootstrap/app.php.
 */
final class ChatbotServiceUnavailableException extends DomainException
{
    public static function gemini(?Throwable $previous = null): self
    {
        return new self(
            'The assistant is unavailable right now — the Gemini API could not be reached. '
            .'Set GEMINI_API_KEY in your .env and try again.',
            503,
            $previous,
        );
    }

    /**
     * Surface Gemini's actual error message (passed through from the response
     * body) so the UI can show the real cause — e.g. "This model is currently
     * experiencing high demand", "API key not valid", "models/x is not found".
     */
    public static function fromGemini(string $message, int $status = 503, ?Throwable $previous = null): self
    {
        return new self($message, $status, $previous);
    }
}
