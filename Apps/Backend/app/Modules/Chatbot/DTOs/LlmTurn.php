<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

/**
 * One conversation turn in provider-neutral form. `parts` is a list of tagged
 * arrays, exactly one tag per part:
 *
 *   ['text' => string]
 *   ['call' => ['name' => string, 'args' => array]]        (assistant tool call)
 *   ['result' => ['name' => string, 'payload' => array]]   (tool result, echoed back)
 *   ['raw' => array]                                        (provider-native part, passed through verbatim)
 *
 * `raw` exists because Gemini 3.x thinking models attach a thoughtSignature to
 * their functionCall parts which must be echoed back byte-for-byte on the next
 * request — reconstructing the turn from parsed calls drops it and the API 400s.
 */
final class LlmTurn
{
    /**
     * @param  'user'|'assistant'  $role
     * @param  list<array<string, mixed>>  $parts
     */
    public function __construct(
        public readonly string $role,
        public readonly array $parts,
    ) {}

    public static function user(string $text): self
    {
        return new self('user', [['text' => $text]]);
    }

    public static function assistant(string $text): self
    {
        return new self('assistant', [['text' => $text]]);
    }

    /**
     * The tool results answering an assistant tool-call turn.
     *
     * @param  list<array{name: string, payload: array<string, mixed>}>  $results
     */
    public static function toolResults(array $results): self
    {
        return new self('user', array_map(
            static fn (array $r): array => ['result' => $r],
            $results,
        ));
    }
}
