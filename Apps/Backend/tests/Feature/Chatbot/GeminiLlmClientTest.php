<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Chatbot\DTOs\LlmMessage;
use App\Modules\Chatbot\DTOs\LlmPart;
use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\Exceptions\ChatbotServiceUnavailableException;
use App\Modules\Chatbot\Services\Llm\GeminiLlmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLlmClientTest extends TestCase
{
    private function client(): GeminiLlmClient
    {
        return new GeminiLlmClient(
            settings: [
                'api_key' => 'test-key',
                'model' => 'gemini-3.5-flash',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => 30,
            ],
            maxTokens: 2048,
            temperature: 0.2,
        );
    }

    private function request(): LlmRequest
    {
        return new LlmRequest(
            systemPrompt: 'You are a test assistant.',
            messages: [LlmMessage::text('user', 'hi')],
            tools: [],
            toolMode: 'auto',
        );
    }

    public function test_parses_a_text_only_response(): void
    {
        $this->fakeGenerate([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'Hello there.']]]]],
        ]);

        $response = $this->client()->generate($this->request());

        $this->assertSame('Hello there.', $response->text);
        $this->assertSame([], $response->functionCalls);
        $this->assertSame('STOP', $response->finishReason);
    }

    public function test_parses_a_function_call_response(): void
    {
        $this->fakeGenerate([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['functionCall' => ['name' => 'get_product', 'args' => ['product_id' => 5]]]]],
            ]],
        ]);

        $response = $this->client()->generate($this->request());

        $this->assertNull($response->text);
        $this->assertCount(1, $response->functionCalls);
        $this->assertSame('get_product', $response->functionCalls[0]['name']);
        $this->assertSame(['product_id' => 5], $response->functionCalls[0]['args']);
    }

    public function test_retries_on_429_then_succeeds(): void
    {
        $this->fakeSequence([
            [['error' => ['message' => 'rate limited']], 429],
            [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'ok']]]]]], 200],
        ]);

        $response = $this->client()->generate($this->request());

        $this->assertSame('ok', $response->text);
        Http::assertSentCount(2);
    }

    public function test_surfaces_503_after_429s_are_exhausted(): void
    {
        $this->fakeSequence([
            [['error' => ['message' => 'rate limited']], 429],
            [['error' => ['message' => 'rate limited']], 429],
            [['error' => ['message' => 'rate limited']], 429],
        ]);

        $this->expectException(ChatbotServiceUnavailableException::class);

        $this->client()->generate($this->request());
    }

    public function test_surfaces_503_on_a_server_error(): void
    {
        $this->fakeGenerate(['error' => ['message' => 'internal']], 500);

        $this->expectException(ChatbotServiceUnavailableException::class);

        $this->client()->generate($this->request());
    }

    public function test_surfaces_503_on_connection_failure(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(ChatbotServiceUnavailableException::class);

        $this->client()->generate($this->request());
    }

    public function test_builds_payload_with_tool_mode_none_when_disabled(): void
    {
        $this->fakeGenerate([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'done']]]]],
        ]);

        $request = new LlmRequest('sys', [LlmMessage::text('user', 'hi')], [], 'none');

        $this->client()->generate($request);

        Http::assertSent(function ($httpRequest): bool {
            $body = json_decode($httpRequest->data(), true);

            return ($body['toolConfig']['functionCallingConfig']['mode'] ?? null) === 'NONE';
        });
    }

    public function test_maps_assistant_role_to_model_and_sends_function_responses_as_user(): void
    {
        $this->fakeGenerate([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'done']]]]],
        ]);

        $messages = [
            LlmMessage::text('user', 'q'),
            new LlmMessage('assistant', [LlmPart::functionCall('get_product', ['product_id' => 1])]),
            new LlmMessage('user', [LlmPart::functionResponse('get_product', ['name' => 'Widget'])]),
        ];

        $this->client()->generate(new LlmRequest('sys', $messages, [], 'auto'));

        Http::assertSent(function ($httpRequest): bool {
            $body = json_decode($httpRequest->data(), true);
            $roles = array_column($body['contents'], 'role');

            return $roles === ['user', 'model', 'user'];
        });
    }

    /** @param array<string, mixed> $body */
    private function fakeGenerate(array $body, int $status = 200): void
    {
        Http::fake(['#generateContent$#i' => Http::response($body, $status)]);
    }

    /** @param list<array{0: array<string, mixed>, 1: int}> $responses */
    private function fakeSequence(array $responses): void
    {
        $sequence = Http::sequence();
        foreach ($responses as [$body, $status]) {
            $sequence->push($body, $status);
        }
        Http::fake(['#generateContent$#i' => $sequence]);
    }
}
