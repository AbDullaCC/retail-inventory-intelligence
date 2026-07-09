<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Exceptions\ChatbotServiceUnavailableException;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/chat/threads')->assertUnauthorized();
        $this->postJson('/api/chat/messages', ['message' => 'hi'])->assertUnauthorized();
    }

    public function test_send_without_thread_id_creates_a_thread_and_persists_both_messages(): void
    {
        $this->fakeLlm(new LlmResponse('You should reorder Cola.', [], 'STOP'));
        \Laravel\Sanctum\Sanctum::actingAs($user = User::factory()->create());

        $response = $this->postJson('/api/chat/messages', ['message' => 'What should I reorder?'])
            ->assertOk();

        $response->assertJsonPath('data.message.content', 'You should reorder Cola.')
            ->assertJsonPath('data.message.role', 'assistant')
            ->assertJsonPath('data.thread.title', 'What should I reorder?');

        $this->assertDatabaseCount('chat_threads', 1);
        $this->assertDatabaseCount('chat_messages', 2);
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'What should I reorder?']);
        $this->assertDatabaseHas('chat_messages', ['role' => 'assistant', 'content' => 'You should reorder Cola.']);
    }

    public function test_send_with_thread_id_appends_to_the_existing_thread(): void
    {
        $this->fakeLlm(new LlmResponse('second answer', [], 'STOP'));
        \Laravel\Sanctum\Sanctum::actingAs($user = User::factory()->create());

        $first = $this->postJson('/api/chat/messages', ['message' => 'first'])->assertOk();
        $threadId = $first->json('data.thread.id');

        $this->postJson('/api/chat/messages', ['message' => 'second', 'thread_id' => $threadId])
            ->assertOk()
            ->assertJsonPath('data.thread.id', $threadId);

        $this->assertDatabaseCount('chat_threads', 1);
        $this->assertDatabaseCount('chat_messages', 4);
    }

    public function test_a_foreign_users_thread_returns_404(): void
    {
        $this->fakeLlm(new LlmResponse('answer', [], 'STOP'));
        \Laravel\Sanctum\Sanctum::actingAs($owner = User::factory()->create());

        $threadId = $this->postJson('/api/chat/messages', ['message' => 'mine'])->assertOk()->json('data.thread.id');

        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', ['message' => 'theirs', 'thread_id' => $threadId])
            ->assertNotFound();
    }

    public function test_thread_show_returns_the_thread_with_its_messages(): void
    {
        $this->fakeLlm(new LlmResponse('answer', [], 'STOP'));
        \Laravel\Sanctum\Sanctum::actingAs($user = User::factory()->create());

        $threadId = $this->postJson('/api/chat/messages', ['message' => 'hello'])->assertOk()->json('data.thread.id');

        $this->getJson("/api/chat/threads/{$threadId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.role', 'user');
    }

    public function test_thread_list_returns_only_the_users_threads(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        \App\Modules\Chatbot\Models\ChatThread::factory()->create(['user_id' => $user->id, 'title' => 'mine']);
        \App\Modules\Chatbot\Models\ChatThread::factory()->create(['user_id' => $other->id, 'title' => 'theirs']);
        \App\Modules\Chatbot\Models\ChatThread::factory()->create(['user_id' => $other->id, 'title' => 'theirs 2']);

        \Laravel\Sanctum\Sanctum::actingAs($user);
        $response = $this->getJson('/api/chat/threads')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('mine', $response->json('data.0.title'));
    }

    public function test_rate_limit_returns_429_after_the_hourly_cap(): void
    {
        config()->set('services.chatbot.rate_limit_per_hour', 2);
        $this->fakeLlm(new LlmResponse('answer', [], 'STOP'));
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', ['message' => 'one'])->assertOk();
        $this->postJson('/api/chat/messages', ['message' => 'two'])->assertOk();
        $this->postJson('/api/chat/messages', ['message' => 'three'])->assertStatus(429);
    }

    public function test_a_gemini_failure_becomes_a_503_but_the_user_message_survives(): void
    {
        $this->fakeLlmThrowing(new ChatbotServiceUnavailableException('down', 503));
        \Laravel\Sanctum\Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/chat/messages', ['message' => 'please answer'])
            ->assertStatus(503);

        // Two-transaction design: the user message persists; only the reply failed.
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'please answer']);
        $this->assertDatabaseMissing('chat_messages', ['role' => 'assistant']);
    }

    public function test_validation_rejects_a_missing_message(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', [])->assertStatus(422);
    }

    /**
     * Swap the LlmClient binding for a deterministic fake so the orchestration
     * loop runs without hitting the network.
     */
    private function fakeLlm(LlmResponse $response): void
    {
        $this->app->bind(LlmClientInterface::class, fn () => new class($response) implements LlmClientInterface {
            public function __construct(private readonly LlmResponse $response) {}

            public function generate(LlmRequest $request): LlmResponse
            {
                return $this->response;
            }

            public function stream(LlmRequest $request): \Generator
            {
                throw new \LogicException('not used');
            }
        });
    }

    private function fakeLlmThrowing(\Throwable $e): void
    {
        $this->app->bind(LlmClientInterface::class, fn () => new class($e) implements LlmClientInterface {
            public function __construct(private readonly \Throwable $e) {}

            public function generate(LlmRequest $request): LlmResponse
            {
                throw $this->e;
            }

            public function stream(LlmRequest $request): \Generator
            {
                throw new \LogicException('not used');
            }
        });
    }
}
