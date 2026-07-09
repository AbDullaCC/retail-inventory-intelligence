<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A provider-agnostic request to the LLM. The Gemini-specific JSON translation
 * (contents, systemInstruction, tools, toolConfig) lives inside
 * {@see \App\Modules\Chatbot\Services\Llm\GeminiLlmClient}.
 */
final class LlmRequest
{
    /**
     * @param  list<LlmMessage>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @param  'auto'|'none'  $toolMode  'none' disables function calling — used for the forced-text final call.
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $messages,
        public readonly array $tools,
        public readonly string $toolMode = 'auto',
    ) {}
}
