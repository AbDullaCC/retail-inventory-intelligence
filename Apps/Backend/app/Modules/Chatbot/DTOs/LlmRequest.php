<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A provider-neutral generation request: system prompt, conversation turns,
 * the declared tools, and whether the model may call them.
 */
final class LlmRequest
{
    /**
     * @param  list<LlmTurn>  $turns
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @param  'auto'|'none'  $toolMode  'none' forbids tool calls — used to force a text answer.
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $turns,
        public readonly array $tools = [],
        public readonly string $toolMode = 'auto',
    ) {}
}
