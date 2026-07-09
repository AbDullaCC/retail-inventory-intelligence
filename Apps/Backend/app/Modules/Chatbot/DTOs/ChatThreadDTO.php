<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * A conversation thread. `messages` is null on the list endpoint and populated
 * on the `show` path (thread + its messages for thread switching).
 */
final class ChatThreadDTO extends BaseData
{
    /**
     * @param  list<ChatMessageDTO>|null  $messages
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly int $messageCount,
        public readonly ?string $lastMessageAt,
        public readonly string $createdAt,
        public readonly ?array $messages = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'message_count' => $this->messageCount,
            'last_message_at' => $this->lastMessageAt,
            'created_at' => $this->createdAt,
            'messages' => $this->messages === null
                ? null
                : array_map(static fn (ChatMessageDTO $m) => $m->toArray(), $this->messages),
        ];
    }
}
