<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

/**
 * What one orchestrator run produces: the assistant's answer text and the
 * citation record of the tools it consulted (name + one-line summary each) —
 * persisted with the message and shown in the UI as "sources".
 */
final class ChatResult
{
    /**
     * @param  list<array{name: string, summary: string}>  $citations
     */
    public function __construct(
        public readonly string $text,
        public readonly array $citations,
    ) {}
}
