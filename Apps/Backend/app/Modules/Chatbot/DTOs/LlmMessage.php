<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * A message in the LLM conversation. `role` is 'user' or 'assistant' (the
 * provider client maps 'assistant' → the provider's model role). `parts` is a
 * list of {@see LlmPart}.
 */
final class LlmMessage
{
    /**
     * @param  'user'|'assistant'  $role
     * @param  list<LlmPart>  $parts
     */
    public function __construct(
        public readonly string $role,
        public readonly array $parts,
    ) {}

    /**
     * @param  'user'|'assistant'  $role
     */
    public static function text(string $role, string $text): self
    {
        return new self($role, [LlmPart::text($text)]);
    }
}
