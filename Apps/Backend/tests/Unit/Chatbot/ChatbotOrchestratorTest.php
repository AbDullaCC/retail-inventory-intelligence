<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Services\ChatbotOrchestrator;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Tools\ChatbotTool;
use App\Modules\Chatbot\Services\Tools\ChatbotToolRegistry;
use App\Modules\Chatbot\Support\SystemPromptBuilder;
use PHPUnit\Framework\TestCase;

class ChatbotOrchestratorTest extends TestCase
{
    public function test_returns_text_directly_when_no_tool_is_called(): void
    {
        $client = new FakeLlmClient([
            new LlmResponse(text: 'Hello!', functionCalls: [], finishReason: 'STOP'),
        ]);

        $result = $this->orchestrator($client)->run([], 'hi');

        $this->assertSame('Hello!', $result->text);
        $this->assertSame([], $result->toolCalls);
    }

    public function test_runs_a_tool_feeds_the_response_back_and_returns_final_text(): void
    {
        $client = new FakeLlmClient([
            new LlmResponse(text: null, functionCalls: [['name' => 'echo', 'args' => ['msg' => 'world']]], finishReason: 'STOP'),
            new LlmResponse(text: 'You said world.', functionCalls: [], finishReason: 'STOP'),
        ]);

        $result = $this->orchestrator($client)->run([], 'echo world');

        $this->assertSame('You said world.', $result->text);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('echo', $result->toolCalls[0]['name']);
        $this->assertSame(['msg' => 'world'], $result->toolCalls[0]['args']);
        $this->assertStringContainsString('echo', $result->toolCalls[0]['result_summary']);
    }

    public function test_unknown_tool_yields_an_error_function_response_not_a_throw(): void
    {
        $client = new FakeLlmClient([
            new LlmResponse(text: null, functionCalls: [['name' => 'no_such_tool', 'args' => []]], finishReason: 'STOP'),
            new LlmResponse(text: 'I could not find that tool.', functionCalls: [], finishReason: 'STOP'),
        ]);

        $result = $this->orchestrator($client)->run([], 'x');

        $this->assertSame('I could not find that tool.', $result->text);
        $this->assertStringContainsString('unknown tool', $result->toolCalls[0]['result_summary']);
    }

    public function test_invalid_args_yield_an_error_function_response(): void
    {
        $client = new FakeLlmClient([
            // echo requires `msg`; calling it without should be rejected.
            new LlmResponse(text: null, functionCalls: [['name' => 'echo', 'args' => []]], finishReason: 'STOP'),
            new LlmResponse(text: 'ok', functionCalls: [], finishReason: 'STOP'),
        ]);

        $result = $this->orchestrator($client)->run([], 'x');

        $this->assertSame('ok', $result->text);
        $this->assertStringContainsString('invalid arguments', $result->toolCalls[0]['result_summary']);
    }

    public function test_at_the_iteration_cap_forces_a_tool_mode_none_call_for_text(): void
    {
        // The model never stops calling tools — every call returns a function call.
        $looping = new LlmResponse(text: null, functionCalls: [['name' => 'echo', 'args' => ['msg' => 'again']]], finishReason: 'STOP');
        $forced = new LlmResponse(text: 'Forced final answer.', functionCalls: [], finishReason: 'STOP');

        $client = new FakeLlmClient(array_fill(0, 5, $looping));
        $client->queue[] = $forced; // the tool_mode:none call

        $result = $this->orchestrator($client, maxIterations: 5)->run([], 'x');

        $this->assertSame('Forced final answer.', $result->text);
        // The final call must have had function calling disabled.
        $this->assertSame('none', end($client->requests)->toolMode);
    }

    public function test_falls_back_to_a_deterministic_summary_when_the_forced_call_also_fails(): void
    {
        $looping = new LlmResponse(text: null, functionCalls: [['name' => 'echo', 'args' => ['msg' => 'again']]], finishReason: 'STOP');
        // The forced call returns empty text (no function calls, since mode=none).
        $empty = new LlmResponse(text: '', functionCalls: [], finishReason: 'MAX_TOKENS');

        $client = new FakeLlmClient(array_fill(0, 5, $looping));
        $client->queue[] = $empty;

        $result = $this->orchestrator($client, maxIterations: 5)->run([], 'x');

        $this->assertStringContainsString('Based on the data I retrieved', $result->text);
    }

    public function test_echoes_provider_raw_parts_when_present_so_thought_signatures_survive(): void
    {
        // Gemini 3.x returns thought parts (carrying a thoughtSignature) next
        // to the functionCall; they must be echoed back verbatim on the next
        // request or the API 400s with "missing a thought_signature".
        $rawParts = [
            ['text' => 'Let me look that up.', 'thought' => true, 'thoughtSignature' => 'sig-abc'],
            ['functionCall' => ['name' => 'echo', 'args' => ['msg' => 'world']]],
        ];

        $client = new FakeLlmClient([
            new LlmResponse(text: null, functionCalls: [['name' => 'echo', 'args' => ['msg' => 'world']]], finishReason: 'STOP', rawParts: $rawParts),
            new LlmResponse(text: 'Done.', functionCalls: [], finishReason: 'STOP'),
        ]);

        $result = $this->orchestrator($client)->run([], 'echo world');

        $this->assertSame('Done.', $result->text);

        // The second request (after the tool ran) must contain the raw model
        // turn as an assistant message with the thought part + signature intact.
        $second = $client->requests[1];
        $this->assertCount(3, $second->messages); // history-text + assistant(raw) + user(responses)

        $assistantTurn = $second->messages[1];
        $this->assertSame('assistant', $assistantTurn->role);
        $this->assertCount(2, $assistantTurn->parts);
        $this->assertNotNull($assistantTurn->parts[0]->raw);
        $this->assertSame('sig-abc', $assistantTurn->parts[0]->raw['thoughtSignature'] ?? null);
    }

    private function orchestrator(FakeLlmClient $client, int $maxIterations = 5): ChatbotOrchestrator
    {
        $echo = new ChatbotTool(
            name: 'echo',
            description: 'Echoes a message.',
            parameters: [
                'type' => 'object',
                'properties' => ['msg' => ['type' => 'string']],
                'required' => ['msg'],
                'additionalProperties' => false,
            ],
            handler: static fn (array $args): array => ['echoed' => $args['msg'] ?? null],
        );

        $registry = new ChatbotToolRegistry([$echo]);

        return new ChatbotOrchestrator(
            client: $client,
            registry: $registry,
            promptBuilder: new SystemPromptBuilder,
            maxIterations: $maxIterations,
        );
    }
}

/**
 * A scriptable LlmClient that returns queued responses in order and records
 * every request (so tests can assert tool_mode etc.).
 */
final class FakeLlmClient implements LlmClientInterface
{
    /** @var list<LlmResponse> */
    public array $queue;

    /** @var list<LlmRequest> */
    public array $requests = [];

    public function __construct(array $responses)
    {
        $this->queue = $responses;
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        return array_shift($this->queue) ?? new LlmResponse(text: '', functionCalls: [], finishReason: 'STOP');
    }

    public function stream(LlmRequest $request): \Generator
    {
        throw new \LogicException('not used');
    }
}
