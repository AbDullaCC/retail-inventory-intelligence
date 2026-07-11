<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A provider-neutral generation response. When the model requested tools,
 * `toolCalls` is non-empty and `rawParts` holds the provider's original
 * response parts so the orchestrator can echo the turn back verbatim
 * (see LlmTurn for why that matters on Gemini 3.x).
 */
final class LlmResponse
{
    /**
     * @param  list<array{name: string, args: array<string, mixed>}>  $toolCalls
     * @param  list<array<string, mixed>>  $rawParts
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls = [],
        public readonly array $rawParts = [],
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
