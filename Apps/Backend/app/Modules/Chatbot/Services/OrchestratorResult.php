<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

/**
 * The orchestrator's output: the assistant's final text answer plus the
 * truncated record of read tools it invoked (for the UI's cited sources and for
 * persistence on the assistant message).
 */
final class OrchestratorResult
{
    /**
     * @param  list<array{name: string, args: array<string, mixed>, result_summary: string}>  $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls,
    ) {}
}
