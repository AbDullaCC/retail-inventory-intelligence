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

    public function test_surfaces_gemini_actual_error_message_verbatim(): void
    {
        // Mirrors the real "model overloaded" response — the user must see this
        // text, not a generic "unavailable" string, so they know it's transient.
        $this->fakeGenerate([
            'error' => [
                'code' => 503,
                'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
                'status' => 'UNAVAILABLE',
            ],
        ], 503);

        try {
            $this->client()->generate($this->request());
            $this->fail('Expected ChatbotServiceUnavailableException.');
        } catch (ChatbotServiceUnavailableException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
            $this->assertStringContainsString('high demand', $e->getMessage());
        }
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
            $body = $httpRequest->data();

            return ($body['toolConfig']['functionCallingConfig']['mode'] ?? null) === 'NONE';
        });
    }

    public function test_strips_gemini_unsupported_schema_keys_from_function_declarations(): void
    {
        // `additionalProperties` is valid JSON Schema but Gemini rejects it with
        // HTTP 400 "Unknown name 'additionalProperties'". The local validator
        // still sees it, but the outbound payload must not.
        $this->fakeGenerate([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'done']]]]],
        ]);

        $tool = [
            'name' => 'get_product',
            'description' => 'one product',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                    'nested' => [
                        'type' => 'object',
                        'properties' => ['x' => ['type' => 'integer']],
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['product_id'],
                'additionalProperties' => false,
                '$schema' => 'http://json-schema.org/draft-07/schema#',
            ],
        ];

        $this->client()->generate(new LlmRequest('sys', [LlmMessage::text('user', 'hi')], [$tool], 'auto'));

        Http::assertSent(function ($httpRequest): bool {
            $decls = $httpRequest->data()['tools'][0]['functionDeclarations'] ?? [];
            $params = $decls[0]['parameters'] ?? [];

            // Top-level unsupported keys gone, but required + properties survive.
            return ! array_key_exists('additionalProperties', $params)
                && ! array_key_exists('$schema', $params)
                && $params['required'] === ['product_id']
                && ! array_key_exists('additionalProperties', $params['properties']['nested']);
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
            $body = $httpRequest->data();
            $roles = array_column($body['contents'], 'role');

            return $roles === ['user', 'model', 'user'];
        });
    }

    /** @param array<string, mixed> $body */
    private function fakeGenerate(array $body, int $status = 200): void
    {
        // Http::fake() matches URL keys with Str::is() (glob `*`), NOT regex —
        // a `#...#` regex key never matches and the request leaks to the real
        // Gemini endpoint. Glob-match the generateContent path instead.
        Http::fake(['*generateContent' => Http::response($body, $status)]);
    }

    /** @param list<array{0: array<string, mixed>, 1: int}> $responses */
    private function fakeSequence(array $responses): void
    {
        $sequence = Http::sequence();
        foreach ($responses as [$body, $status]) {
            $sequence->push($body, $status);
        }
        Http::fake(['*generateContent' => $sequence]);
    }
}
