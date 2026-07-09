<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * One part of an LLM message. A message is a list of parts so a single turn
 * can carry text alongside function calls/responses — the in-flight tool loop
 * appends function-response parts and re-sends the whole conversation.
 *
 * Exactly one of `text`, `functionCall`, `functionResponse` is non-null.
 */
final class LlmPart
{
    private function __construct(
        public readonly ?string $text,
        public readonly ?array $functionCall,
        public readonly ?array $functionResponse,
    ) {}

    public static function text(string $text): self
    {
        return new self(text: $text, functionCall: null, functionResponse: null);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function functionCall(string $name, array $args): self
    {
        return new self(text: null, functionCall: ['name' => $name, 'args' => $args], functionResponse: null);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function functionResponse(string $name, array $response): self
    {
        return new self(text: null, functionCall: null, functionResponse: ['name' => $name, 'response' => $response]);
    }
}
