<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

use App\Modules\Shared\DTOs\BaseData;

final class ChatThreadDTO extends BaseData
{
    /**
     * @param  list<ChatMessageDTO>|null  $messages  Only present on the thread-detail endpoint.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly int $messageCount,
        public readonly ?string $lastMessageAt,
        public readonly string $createdAt,
        public readonly ?array $messages = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'message_count' => $this->messageCount,
            'last_message_at' => $this->lastMessageAt,
            'created_at' => $this->createdAt,
        ];

        if ($this->messages !== null) {
            $data['messages'] = array_map(
                static fn (ChatMessageDTO $m): array => $m->toArray(),
                $this->messages,
            );
        }

        return $data;
    }
}
