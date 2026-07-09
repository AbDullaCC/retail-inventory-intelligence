<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * The send-message response: the thread the message belongs to (created when
 * none was supplied) plus the assistant's reply.
 */
final class ChatAnswerDTO extends BaseData
{
    public function __construct(
        public readonly ChatThreadDTO $thread,
        public readonly ChatMessageDTO $message,
    ) {}

    public function toArray(): array
    {
        return [
            'thread' => $this->thread->toArray(),
            'message' => $this->message->toArray(),
        ];
    }
}
