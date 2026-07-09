<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A provider-agnostic response from the LLM. `text` is null when the model
 * chose to call a tool instead; `functionCalls` is empty when it answered in
 * text. `finishReason` is the provider's stop signal (STOP, MAX_TOKENS, …).
 */
final class LlmResponse
{
    /**
     * @param  list<array{name: string, args: array<string, mixed>}>  $functionCalls
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $functionCalls,
        public readonly string $finishReason,
    ) {}

    public function wantsTools(): bool
    {
        return $this->functionCalls !== [];
    }
}
