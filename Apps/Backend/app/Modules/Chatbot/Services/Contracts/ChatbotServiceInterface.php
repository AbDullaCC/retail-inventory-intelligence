<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Contracts;

use App\Modules\Chatbot\DTOs\ChatAnswerDTO;
use App\Modules\Chatbot\DTOs\ChatThreadDTO;

/**
 * The top-level chatbot service: thread lifecycle + the ask() entry point that
 * persists the conversation and orchestrates the LLM tool loop.
 */
interface ChatbotServiceInterface
{
    /**
     * Answer a user's message, creating a thread when none is given. Persists
     * both the user message and the assistant's reply.
     */
    public function ask(int $userId, ?int $threadId, string $message): ChatAnswerDTO;

    public function createThread(int $userId, ?string $title): ChatThreadDTO;

    /**
     * @return list<ChatThreadDTO>
     */
    public function threads(int $userId): array;

    /** A thread with its messages; 404 when the thread is missing or foreign. */
    public function thread(int $userId, int $threadId): ChatThreadDTO;
}
