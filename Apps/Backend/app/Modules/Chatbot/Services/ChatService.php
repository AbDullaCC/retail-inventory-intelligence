<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

use App\Modules\Chatbot\DTOs\ChatAnswerDTO;
use App\Modules\Chatbot\DTOs\ChatMessageDTO;
use App\Modules\Chatbot\DTOs\ChatThreadDTO;
use App\Modules\Chatbot\DTOs\LlmTurn;
use App\Modules\Chatbot\Exceptions\ChatRateLimitException;
use App\Modules\Chatbot\Models\ChatMessage;
use App\Modules\Chatbot\Models\ChatThread;
use App\Modules\Chatbot\Services\Contracts\ChatServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Thread lifecycle + the send() entry point.
 *
 * send() deliberately keeps the LLM round-trips OUTSIDE any DB transaction:
 * the tool loop can make several sequential HTTP calls, and wrapping it would
 * pin a connection open for their whole duration — worse, a provider failure
 * would roll back (silently delete) the user's message. Instead two small
 * transactions bracket the orchestrator, so on failure the user message is
 * already saved and the client can simply retry.
 */
final class ChatService implements ChatServiceInterface
{
    private const TITLE_LIMIT = 60;

    public function __construct(
        private readonly ChatOrchestrator $orchestrator,
        private readonly int $maxHistoryMessages,
        private readonly int $rateLimitPerHour,
    ) {}

    public function send(int $userId, ?int $threadId, string $message): ChatAnswerDTO
    {
        $this->hitRateLimit($userId);

        // Transaction #1 — resolve/create the thread, persist the user message.
        /** @var array{0: ChatThread, 1: ChatMessage} $saved */
        $saved = DB::transaction(function () use ($userId, $threadId, $message): array {
            $thread = $threadId !== null
                ? $this->ownedThread($userId, $threadId)
                : ChatThread::query()->create([
                    'user_id' => $userId,
                    'title' => Str::limit(trim($message), self::TITLE_LIMIT, '…'),
                    'last_message_at' => now(),
                ]);

            $userMessage = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'role' => 'user',
                'content' => $message,
                'tool_calls' => null,
            ]);

            return [$thread, $userMessage];
        });

        [$thread, $userMessage] = $saved;

        $history = $this->historyTurns($thread->id, $userMessage->id);

        // LLM round-trips — transaction-free on purpose (see class docblock).
        $result = $this->orchestrator->run($history, $message);

        // Transaction #2 — persist the reply.
        $reply = DB::transaction(function () use ($thread, $result): ChatMessage {
            $reply = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $result->text,
                'tool_calls' => $result->citations !== [] ? $result->citations : null,
            ]);

            $thread->update(['last_message_at' => now()]);

            return $reply;
        });

        $thread->loadCount('messages');

        return new ChatAnswerDTO(
            thread: $this->threadDTO($thread),
            message: $this->messageDTO($reply),
        );
    }

    public function threads(int $userId): array
    {
        return ChatThread::query()
            ->where('user_id', $userId)
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChatThread $thread): ChatThreadDTO => $this->threadDTO($thread))
            ->all();
    }

    public function thread(int $userId, int $threadId): ChatThreadDTO
    {
        $thread = $this->ownedThread($userId, $threadId);
        $thread->loadCount('messages');

        $messages = $thread->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $message): ChatMessageDTO => $this->messageDTO($message))
            ->all();

        return $this->threadDTO($thread, $messages);
    }

    /**
     * Fetch a thread enforcing ownership. Missing and foreign threads 404
     * identically so thread ids don't leak across users.
     */
    private function ownedThread(int $userId, int $threadId): ChatThread
    {
        $thread = ChatThread::query()
            ->where('id', $threadId)
            ->where('user_id', $userId)
            ->first();

        if ($thread === null) {
            throw (new ModelNotFoundException)->setModel(ChatThread::class, $threadId);
        }

        return $thread;
    }

    private function hitRateLimit(int $userId): void
    {
        $key = 'chatbot:'.$userId;

        if (RateLimiter::tooManyAttempts($key, $this->rateLimitPerHour)) {
            throw ChatRateLimitException::forLimit($this->rateLimitPerHour);
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * The recent history window (excluding the just-saved user message),
     * oldest first, as plain-text turns.
     *
     * @return list<LlmTurn>
     */
    private function historyTurns(int $threadId, int $excludeMessageId): array
    {
        return ChatMessage::query()
            ->where('thread_id', $threadId)
            ->where('id', '<', $excludeMessageId)
            ->orderByDesc('id')
            ->limit($this->maxHistoryMessages)
            ->get()
            ->reverse()
            ->values()
            ->map(static fn (ChatMessage $m): LlmTurn => $m->role === 'assistant'
                ? LlmTurn::assistant($m->content)
                : LlmTurn::user($m->content))
            ->all();
    }

    /**
     * @param  list<ChatMessageDTO>|null  $messages
     */
    private function threadDTO(ChatThread $thread, ?array $messages = null): ChatThreadDTO
    {
        return new ChatThreadDTO(
            id: $thread->id,
            title: $thread->title,
            messageCount: (int) ($thread->messages_count ?? 0),
            lastMessageAt: $thread->last_message_at?->toIso8601String(),
            createdAt: $thread->created_at->toIso8601String(),
            messages: $messages,
        );
    }

    private function messageDTO(ChatMessage $message): ChatMessageDTO
    {
        return new ChatMessageDTO(
            id: $message->id,
            threadId: $message->thread_id,
            role: $message->role,
            content: $message->content,
            toolCalls: $message->tool_calls,
            createdAt: $message->created_at->toIso8601String(),
        );
    }
}
