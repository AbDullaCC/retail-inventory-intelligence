<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A provider-agnostic response from the LLM. `text` is null when the model
 * chose to call a tool instead; `functionCalls` is empty when it answered in
 * text. `finishReason` is the provider's stop signal (STOP, MAX_TOKENS, …).
 *
 * `rawParts` carries the provider's original response parts when the model
 * emitted parts the agnostic model can't represent but must be echoed back on
 * the next turn — e.g. Gemini 3.x thought parts with their `thoughtSignature`.
 * Empty for providers that don't need it; the orchestrator only appends a raw
 * assistant turn when it's non-empty.
 */
final class LlmResponse
{
    /**
     * @param  list<array{name: string, args: array<string, mixed>}>  $functionCalls
     * @param  list<array<string, mixed>>  $rawParts
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $functionCalls,
        public readonly string $finishReason,
        public readonly array $rawParts = [],
    ) {}

    public function wantsTools(): bool
    {
        return $this->functionCalls !== [];
    }
}
