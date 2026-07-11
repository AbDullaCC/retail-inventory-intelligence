<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmTurn;
use App\Modules\Chatbot\Exceptions\ChatUnavailableException;
use App\Modules\Chatbot\Services\Llm\GeminiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GeminiClient against a faked HTTP layer.
 *
 * NOTE: Http::fake() URL keys are matched with glob (*), NOT regex — a regex
 * key silently matches nothing and the request escapes to the real API.
 */
class GeminiClientTest extends TestCase
{
    private function client(string $apiKey = 'test-key'): GeminiClient
    {
        return new GeminiClient(
            apiKey: $apiKey,
            model: 'gemini-test',
            baseUrl: 'https://gemini.fake/v1beta',
            timeout: 5,
            maxTokens: 512,
            temperature: 0.2,
            retryDelayMs: 0,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function candidate(array $parts): array
    {
        return ['candidates' => [['content' => ['parts' => $parts, 'role' => 'model']]]];
    }

    private function request(string $toolMode = 'auto', array $tools = []): LlmRequest
    {
        return new LlmRequest('system prompt', [LlmTurn::user('hi')], $tools, $toolMode);
    }

    public function test_throws_a_clear_error_when_no_api_key_is_configured(): void
    {
        Http::fake();

        try {
            $this->client(apiKey: '')->generate($this->request());
            $this->fail('Expected ChatUnavailableException.');
        } catch (ChatUnavailableException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringContainsString('GEMINI_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_parses_a_text_response(): void
    {
        Http::fake(['*generateContent' => Http::response($this->candidate([['text' => 'Hello!']]))]);

        $response = $this->client()->generate($this->request());

        $this->assertSame('Hello!', $response->text);
        $this->assertFalse($response->wantsTools());
    }

    public function test_parses_function_calls_and_keeps_raw_parts(): void
    {
        $parts = [
            ['text' => 'thinking…', 'thought' => true, 'thoughtSignature' => 'sig'],
            ['functionCall' => ['name' => 'find_product', 'args' => ['query' => 'mug']]],
        ];
        Http::fake(['*generateContent' => Http::response($this->candidate($parts))]);

        $response = $this->client()->generate($this->request());

        $this->assertTrue($response->wantsTools());
        $this->assertSame([['name' => 'find_product', 'args' => ['query' => 'mug']]], $response->toolCalls);
        $this->assertSame($parts, $response->rawParts);
        // Thought text must never become answer text.
        $this->assertNull($response->text);
    }

    public function test_maps_roles_and_part_kinds_to_gemini_wire_format(): void
    {
        Http::fake(['*generateContent' => Http::response($this->candidate([['text' => 'ok']]))]);

        $turns = [
            LlmTurn::user('question'),
            new LlmTurn('assistant', [['call' => ['name' => 'tool_a', 'args' => []]]]),
            LlmTurn::toolResults([['name' => 'tool_a', 'payload' => ['x' => 1]]]),
        ];

        $this->client()->generate(new LlmRequest('sys', $turns));

        Http::assertSent(function (Request $request): bool {
            $contents = $request->data()['contents'];

            return $contents[0]['role'] === 'user'
                && $contents[1]['role'] === 'model'
                // empty args must serialise as an object, not a list
                && $contents[1]['parts'][0]['functionCall']['args'] instanceof \stdClass
                && $contents[2]['role'] === 'user'
                && $contents[2]['parts'][0]['functionResponse'] === ['name' => 'tool_a', 'response' => ['x' => 1]];
        });
    }

    public function test_echoed_raw_parts_normalise_empty_args_to_an_object(): void
    {
        Http::fake(['*generateContent' => Http::response($this->candidate([['text' => 'ok']]))]);

        $raw = ['functionCall' => ['name' => 'tool_a', 'args' => []], 'thoughtSignature' => 'sig-1'];
        $turns = [new LlmTurn('assistant', [['raw' => $raw]])];

        $this->client()->generate(new LlmRequest('sys', $turns));

        Http::assertSent(function (Request $request): bool {
            $part = $request->data()['contents'][0]['parts'][0];

            return $part['thoughtSignature'] === 'sig-1'
                && $part['functionCall']['args'] instanceof \stdClass;
        });
    }

    public function test_strips_schema_keys_gemini_rejects_and_omits_empty_parameters(): void
    {
        Http::fake(['*generateContent' => Http::response($this->candidate([['text' => 'ok']]))]);

        $tools = [
            [
                'name' => 'with_args',
                'description' => 'd',
                'parameters' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'nested' => ['type' => 'object', 'additionalProperties' => false, 'properties' => []],
                    ],
                ],
            ],
            ['name' => 'no_args', 'description' => 'd', 'parameters' => []],
        ];

        $this->client()->generate($this->request(tools: $tools));

        Http::assertSent(function (Request $request): bool {
            $declarations = $request->data()['tools'][0]['functionDeclarations'];

            return ! array_key_exists('additionalProperties', $declarations[0]['parameters'])
                && ! array_key_exists('additionalProperties', $declarations[0]['parameters']['properties']['nested'])
                && ! array_key_exists('parameters', $declarations[1]);
        });
    }

    public function test_tool_mode_none_sends_the_disabling_tool_config(): void
    {
        Http::fake(['*generateContent' => Http::response($this->candidate([['text' => 'ok']]))]);

        $this->client()->generate($this->request(toolMode: 'none'));

        Http::assertSent(fn (Request $request): bool => $request->data()['toolConfig']['functionCallingConfig']['mode'] === 'NONE');
    }

    public function test_retries_a_429_then_succeeds(): void
    {
        Http::fake([
            '*generateContent' => Http::sequence()
                ->push(['error' => ['message' => 'rate limited']], 429)
                ->push($this->candidate([['text' => 'after retry']])),
        ]);

        $response = $this->client()->generate($this->request());

        $this->assertSame('after retry', $response->text);
        Http::assertSentCount(2);
    }

    public function test_surfaces_the_providers_error_message_verbatim_as_503(): void
    {
        Http::fake([
            '*generateContent' => Http::response(['error' => ['message' => 'API key not valid. Please pass a valid API key.']], 400),
        ]);

        try {
            $this->client()->generate($this->request());
            $this->fail('Expected ChatUnavailableException.');
        } catch (ChatUnavailableException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringContainsString('API key not valid', $e->getMessage());
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
        }
    }

    public function test_exhausted_429_retries_surface_as_503(): void
    {
        Http::fake(['*generateContent' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        try {
            $this->client()->generate($this->request());
            $this->fail('Expected ChatUnavailableException.');
        } catch (ChatUnavailableException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringContainsString('quota exceeded', $e->getMessage());
            Http::assertSentCount(3);
        }
    }
}
