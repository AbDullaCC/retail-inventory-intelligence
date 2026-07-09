<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

use App\Modules\Chatbot\DTOs\ChatAnswerDTO;
use App\Modules\Chatbot\DTOs\ChatMessageDTO;
use App\Modules\Chatbot\DTOs\ChatThreadDTO;
use App\Modules\Chatbot\DTOs\LlmMessage;
use App\Modules\Chatbot\Exceptions\ChatbotRateLimitException;
use App\Modules\Chatbot\Models\ChatMessage;
use App\Modules\Chatbot\Models\ChatThread;
use App\Modules\Chatbot\Services\Contracts\ChatbotServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The top-level chatbot service: thread lifecycle + the ask() entry point.
 *
 * ask() deliberately does NOT wrap the LLM round-trips in a single transaction
 * — up to 5 sequential HTTP calls would pin a MySQL connection inside an open
 * transaction for minutes, and a rollback on a Gemini failure would silently
 * delete the user's message. Instead two small transactions bracket the
 * (transaction-free) orchestrator call, so a provider failure leaves the user
 * message intact and the UI can offer retry.
 */
final class ChatbotService implements ChatbotServiceInterface
{
    public function __construct(
        private readonly ChatbotOrchestrator $orchestrator,
        private readonly int $maxHistoryMessages,
        private readonly int $rateLimitPerHour,
    ) {}

    public function ask(int $userId, ?int $threadId, string $message): ChatAnswerDTO
    {
        $this->assertRateLimit($userId);

        // Transaction #1: resolve/create thread + persist the user message.
        [$thread, $userMessage] = DB::transaction(function () use ($userId, $threadId, $message): array {
            if ($threadId !== null) {
                $thread = ChatThread::query()
                    ->where('id', $threadId)
                    ->where('user_id', $userId)
                    ->firstOrFail();
            } else {
                $thread = ChatThread::query()->create([
                    'user_id' => $userId,
                    'title' => $this->titleFrom($message),
                    'last_message_at' => now(),
                ]);
            }

            $userMessage = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'role' => 'user',
                'content' => $message,
                'tool_calls' => null,
            ]);

            // On a thread's first message, label it with the prompt.
            if ($threadId === null) {
                $thread->update(['last_message_at' => now()]);
            }

            return [$thread, $userMessage];
        });

        // Load the recent history window (the orchestrator runs transaction-free).
        $history = $this->loadHistory($thread->id, $userMessage->id);

        $result = $this->orchestrator->run($history, $message);

        // Transaction #2: persist the assistant reply + bump last_message_at.
        $assistantMessage = DB::transaction(function () use ($thread, $result): ChatMessage {
            $reply = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $result->text,
                'tool_calls' => $result->toolCalls ?: null,
            ]);

            $thread->update(['last_message_at' => now()]);

            return $reply;
        });

        $thread->loadCount('messages');

        return new ChatAnswerDTO(
            thread: $this->threadDTO($thread, null),
            message: $this->messageDTO($assistantMessage),
        );
    }

    public function createThread(int $userId, ?string $title): ChatThreadDTO
    {
        $thread = ChatThread::query()->create([
            'user_id' => $userId,
            'title' => $title !== null && trim($title) !== '' ? trim($title) : 'New chat',
            'last_message_at' => null,
        ]);

        $thread->loadCount('messages');

        return $this->threadDTO($thread, null);
    }

    public function threads(int $userId): array
    {
        $threads = ChatThread::query()
            ->where('user_id', $userId)
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return $threads->map(fn (ChatThread $t): ChatThreadDTO => $this->threadDTO($t, null))->all();
    }

    public function thread(int $userId, int $threadId): ChatThreadDTO
    {
        $thread = ChatThread::query()
            ->where('id', $threadId)
            ->where('user_id', $userId)
            ->withCount('messages')
            ->first();

        if ($thread === null) {
            throw (new ModelNotFoundException)->setModel(ChatThread::class, $threadId);
        }

        $messages = $thread->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatMessage $m): ChatMessageDTO => $this->messageDTO($m))
            ->all();

        return $this->threadDTO($thread, $messages);
    }

    private function assertRateLimit(int $userId): void
    {
        $key = "chatbot:{$userId}";

        if (RateLimiter::tooManyAttempts($key, $this->rateLimitPerHour)) {
            throw ChatbotRateLimitException::forLimit($this->rateLimitPerHour);
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * Load the most recent history (excluding the just-saved user message),
     * oldest-first, capped at the configured window.
     *
     * @return list<LlmMessage>
     */
    private function loadHistory(int $threadId, int $latestMessageId): array
    {
        $messages = ChatMessage::query()
            ->where('thread_id', $threadId)
            ->where('id', '<', $latestMessageId)
            ->orderByDesc('id')
            ->limit($this->maxHistoryMessages)
            ->get()
            ->reverse()
            ->values();

        return $messages
            ->map(static fn (ChatMessage $m): LlmMessage => LlmMessage::text(
                $m->role === 'assistant' ? 'assistant' : 'user',
                $m->content,
            ))
            ->all();
    }

    private function titleFrom(string $message): string
    {
        $title = trim($message);

        return mb_strlen($title) > 50 ? mb_substr($title, 0, 50).'…' : $title;
    }

    private function threadDTO(ChatThread $thread, ?array $messages): ChatThreadDTO
    {
        return new ChatThreadDTO(
            id: $thread->id,
            userId: $thread->user_id,
            title: $thread->title,
            messageCount: (int) ($thread->messages_count ?? $thread->messages()->count()),
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
