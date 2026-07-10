<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * One part of an LLM message. A message is a list of parts so a single turn
 * can carry text alongside function calls/responses — the in-flight tool loop
 * appends function-response parts and re-sends the whole conversation.
 *
 * Exactly one of `text`, `functionCall`, `functionResponse`, `raw` is non-null.
 * `raw` is a provider-detail escape hatch for parts the provider-agnostic model
 * can't represent but must be echoed back verbatim — e.g. Gemini 3.x "thought"
 * parts carrying a `thoughtSignature`, which the API requires to accompany the
 * model's own functionCall turns on the next request.
 */
final class LlmPart
{
    private function __construct(
        public readonly ?string $text,
        public readonly ?array $functionCall,
        public readonly ?array $functionResponse,
        public readonly ?array $raw,
    ) {}

    public static function text(string $text): self
    {
        return new self(text: $text, functionCall: null, functionResponse: null, raw: null);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function functionCall(string $name, array $args): self
    {
        return new self(text: null, functionCall: ['name' => $name, 'args' => $args], functionResponse: null, raw: null);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function functionResponse(string $name, array $response): self
    {
        return new self(text: null, functionCall: null, functionResponse: ['name' => $name, 'response' => $response], raw: null);
    }

    /**
     * A raw provider part, echoed back verbatim by the client. Use for parts
     * the provider-agnostic model cannot represent (e.g. Gemini thought parts
     * with their thoughtSignature) that must be preserved between turns.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function raw(array $raw): self
    {
        return new self(text: null, functionCall: null, functionResponse: null, raw: $raw);
    }
}
