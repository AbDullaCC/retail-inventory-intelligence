<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Contracts;

use App\Modules\Chatbot\DTOs\ChatAnswerDTO;
use App\Modules\Chatbot\DTOs\ChatThreadDTO;

interface ChatServiceInterface
{
    /**
     * Send a user message (into an existing thread, or a new one when
     * $threadId is null) and return the assistant's reply.
     */
    public function send(int $userId, ?int $threadId, string $message): ChatAnswerDTO;

    /**
     * The user's threads, newest activity first (without messages).
     *
     * @return list<ChatThreadDTO>
     */
    public function threads(int $userId): array;

    /**
     * One thread with its full message history. 404s uniformly for missing
     * and foreign threads.
     */
    public function thread(int $userId, int $threadId): ChatThreadDTO;
}
