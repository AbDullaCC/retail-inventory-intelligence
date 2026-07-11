<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Auth\Models\User;
use App\Modules\Chatbot\Models\ChatMessage;
use App\Modules\Chatbot\Models\ChatThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The chat API end to end — real container wiring (provider, registry,
 * orchestrator, GeminiClient) against a faked Gemini HTTP layer and the
 * in-memory database.
 */
class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.chatbot', [
            'gemini' => [
                'api_key' => 'test-key',
                'model' => 'gemini-test',
                'base_url' => 'https://gemini.fake/v1beta',
                'timeout' => 5,
            ],
            'max_tokens' => 512,
            'temperature' => 0.2,
            'max_history_messages' => 12,
            'max_tool_iterations' => 5,
            'max_tool_result_items' => 20,
            'rate_limit_per_hour' => 30,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function geminiTurn(array $parts): array
    {
        return ['candidates' => [['content' => ['parts' => $parts, 'role' => 'model']]]];
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/chat/threads')->assertUnauthorized();
        $this->getJson('/api/chat/threads/1')->assertUnauthorized();
        $this->postJson('/api/chat/messages')->assertUnauthorized();
    }

    public function test_send_without_thread_id_creates_a_thread_and_persists_both_messages(): void
    {
        Http::fake(['*generateContent' => Http::response($this->geminiTurn([['text' => 'You have 3 products.']]))]);
        Sanctum::actingAs($user = User::factory()->create());

        $response = $this->postJson('/api/chat/messages', ['message' => 'How many products do I have?'])
            ->assertOk()
            ->assertJsonPath('data.message.role', 'assistant')
            ->assertJsonPath('data.message.content', 'You have 3 products.')
            ->assertJsonPath('data.thread.title', 'How many products do I have?')
            ->assertJsonPath('data.thread.message_count', 2);

        $threadId = $response->json('data.thread.id');

        $this->assertDatabaseHas('chat_threads', ['id' => $threadId, 'user_id' => $user->id]);
        $this->assertSame(
            ['user', 'assistant'],
            ChatMessage::query()->where('thread_id', $threadId)->orderBy('id')->pluck('role')->all(),
        );
    }

    public function test_send_with_thread_id_appends_to_the_existing_thread(): void
    {
        Http::fake(['*generateContent' => Http::response($this->geminiTurn([['text' => 'Second answer.']]))]);
        Sanctum::actingAs($user = User::factory()->create());

        $thread = ChatThread::factory()->for($user)->create();
        ChatMessage::factory()->for($thread, 'thread')->fromUser()->create(['content' => 'first question']);
        ChatMessage::factory()->for($thread, 'thread')->fromAssistant()->create(['content' => 'first answer']);

        $this->postJson('/api/chat/messages', ['message' => 'and now?', 'thread_id' => $thread->id])
            ->assertOk()
            ->assertJsonPath('data.thread.id', $thread->id)
            ->assertJsonPath('data.thread.message_count', 4);
    }

    public function test_a_foreign_users_thread_404s(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());

        $foreign = ChatThread::factory()->create(); // different user

        $this->postJson('/api/chat/messages', ['message' => 'hi', 'thread_id' => $foreign->id])
            ->assertNotFound();
        $this->getJson('/api/chat/threads/'.$foreign->id)->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_prior_history_is_sent_to_the_model(): void
    {
        Http::fake(['*generateContent' => Http::response($this->geminiTurn([['text' => 'With context.']]))]);
        Sanctum::actingAs($user = User::factory()->create());

        $thread = ChatThread::factory()->for($user)->create();
        ChatMessage::factory()->for($thread, 'thread')->fromUser()->create(['content' => 'earlier question']);
        ChatMessage::factory()->for($thread, 'thread')->fromAssistant()->create(['content' => 'earlier answer']);

        $this->postJson('/api/chat/messages', ['message' => 'follow-up', 'thread_id' => $thread->id])->assertOk();

        Http::assertSent(function ($request): bool {
            $contents = $request->data()['contents'];

            return count($contents) === 3
                && $contents[0]['parts'][0]['text'] === 'earlier question'
                && $contents[1]['role'] === 'model'
                && $contents[2]['parts'][0]['text'] === 'follow-up';
        });
    }

    public function test_a_tool_round_trip_persists_citations_on_the_assistant_message(): void
    {
        Http::fake([
            '*generateContent' => Http::sequence()
                ->push($this->geminiTurn([['functionCall' => ['name' => 'get_store_overview', 'args' => []]]]))
                ->push($this->geminiTurn([['text' => 'Your store holds 0 products.']])),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', ['message' => 'how is my store doing?'])
            ->assertOk()
            ->assertJsonPath('data.message.content', 'Your store holds 0 products.')
            ->assertJsonPath('data.message.tool_calls.0.name', 'get_store_overview');

        Http::assertSentCount(2);
    }

    public function test_a_gemini_failure_is_a_503_but_the_user_message_survives(): void
    {
        Http::fake([
            '*generateContent' => Http::response(['error' => ['message' => 'The model is overloaded.']], 503),
        ]);
        Sanctum::actingAs($user = User::factory()->create());

        $response = $this->postJson('/api/chat/messages', ['message' => 'hello?']);

        $response->assertStatus(503);
        $this->assertStringContainsString('The model is overloaded.', (string) $response->json('message'));

        // The user's message was persisted before the LLM call — retry-safe.
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'hello?']);
        $this->assertDatabaseMissing('chat_messages', ['role' => 'assistant']);
    }

    public function test_the_hourly_rate_limit_returns_429(): void
    {
        config()->set('services.chatbot.rate_limit_per_hour', 2);
        Http::fake(['*generateContent' => Http::response($this->geminiTurn([['text' => 'ok']]))]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', ['message' => 'one'])->assertOk();
        $this->postJson('/api/chat/messages', ['message' => 'two'])->assertOk();

        $limited = $this->postJson('/api/chat/messages', ['message' => 'three']);
        $limited->assertStatus(429);
        $this->assertStringContainsString('limit of 2', (string) $limited->json('message'));
    }

    public function test_validation_rejects_a_missing_or_oversized_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat/messages', [])->assertStatus(422);
        $this->postJson('/api/chat/messages', ['message' => str_repeat('x', 2001)])->assertStatus(422);
    }

    public function test_thread_list_returns_only_the_users_threads_newest_first(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $old = ChatThread::factory()->for($user)->create(['last_message_at' => now()->subDay()]);
        $new = ChatThread::factory()->for($user)->create(['last_message_at' => now()]);
        ChatThread::factory()->create(); // someone else's

        $this->getJson('/api/chat/threads')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id);
    }

    public function test_thread_show_returns_the_messages_in_order(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $thread = ChatThread::factory()->for($user)->create();
        ChatMessage::factory()->for($thread, 'thread')->fromUser()->create(['content' => 'q']);
        ChatMessage::factory()->for($thread, 'thread')->fromAssistant()->create([
            'content' => 'a',
            'tool_calls' => [['name' => 'get_store_overview', 'summary' => 'get_store_overview: 8 fields']],
        ]);

        $this->getJson('/api/chat/threads/'.$thread->id)
            ->assertOk()
            ->assertJsonPath('data.message_count', 2)
            ->assertJsonPath('data.messages.0.content', 'q')
            ->assertJsonPath('data.messages.1.content', 'a')
            ->assertJsonPath('data.messages.1.tool_calls.0.name', 'get_store_overview');
    }
}
