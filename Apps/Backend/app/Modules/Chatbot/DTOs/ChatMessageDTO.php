<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * A single chat message, serialised for the API. `tool_calls` is the truncated
 * record of read tools the assistant invoked — never the full payloads.
 */
final class ChatMessageDTO extends BaseData
{
    /**
     * @param  list<array{name: string, args: array<string, mixed>, result_summary: string}>|null  $toolCalls
     */
    public function __construct(
        public readonly int $id,
        public readonly int $threadId,
        public readonly string $role,
        public readonly string $content,
        public readonly ?array $toolCalls,
        public readonly string $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->threadId,
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'created_at' => $this->createdAt,
        ];
    }
}
