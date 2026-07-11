<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Services\ChatOrchestrator;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Tools\Tool;
use App\Modules\Chatbot\Services\Tools\ToolRegistry;
use RuntimeException;
use Tests\TestCase;

/**
 * The tool loop, tested against a scripted fake LLM — no HTTP. (Laravel
 * TestCase because the orchestrator report()s unexpected tool exceptions.)
 */
final class ChatOrchestratorTest extends TestCase
{
    /** @var list<LlmRequest> */
    private array $requests = [];

    public function test_returns_text_directly_when_no_tool_is_called(): void
    {
        $orchestrator = $this->orchestrator([new LlmResponse('Plain answer.')]);

        $result = $orchestrator->run([], 'hello');

        $this->assertSame('Plain answer.', $result->text);
        $this->assertSame([], $result->citations);
        $this->assertCount(1, $this->requests);
    }

    public function test_runs_a_tool_and_feeds_the_result_back(): void
    {
        $orchestrator = $this->orchestrator([
            new LlmResponse(null, [['name' => 'get_number', 'args' => []]]),
            new LlmResponse('The number is 42.'),
        ]);

        $result = $orchestrator->run([], 'what is the number?');

        $this->assertSame('The number is 42.', $result->text);
        $this->assertCount(1, $result->citations);
        $this->assertSame('get_number', $result->citations[0]['name']);

        // Second request must contain the assistant tool turn + the result turn.
        $turns = $this->requests[1]->turns;
        $this->assertCount(3, $turns); // user, assistant call, tool results
        $this->assertSame('assistant', $turns[1]->role);
        $this->assertSame('user', $turns[2]->role);
        $this->assertSame(
            ['result' => ['name' => 'get_number', 'payload' => ['number' => 42]]],
            $turns[2]->parts[0],
        );
    }

    public function test_unknown_tool_becomes_an_error_payload_not_an_exception(): void
    {
        $orchestrator = $this->orchestrator([
            new LlmResponse(null, [['name' => 'not_a_tool', 'args' => []]]),
            new LlmResponse('Recovered.'),
        ]);

        $result = $orchestrator->run([], 'x');

        $this->assertSame('Recovered.', $result->text);
        $payload = $this->requests[1]->turns[2]->parts[0]['result']['payload'];
        $this->assertArrayHasKey('error', $payload);
    }

    public function test_invalid_args_become_an_error_payload(): void
    {
        $orchestrator = $this->orchestrator([
            new LlmResponse(null, [['name' => 'needs_id', 'args' => ['product_id' => 'not-a-number']]]),
            new LlmResponse('Recovered.'),
        ]);

        $result = $orchestrator->run([], 'x');

        $payload = $this->requests[1]->turns[2]->parts[0]['result']['payload'];
        $this->assertSame(['error' => 'Invalid arguments for this tool.'], $payload);
        $this->assertSame('Recovered.', $result->text);
    }

    public function test_a_throwing_tool_becomes_an_error_payload(): void
    {
        $orchestrator = $this->orchestrator([
            new LlmResponse(null, [['name' => 'explodes', 'args' => []]]),
            new LlmResponse('Recovered.'),
        ]);

        $result = $orchestrator->run([], 'x');

        $payload = $this->requests[1]->turns[2]->parts[0]['result']['payload'];
        $this->assertSame(['error' => 'The tool failed unexpectedly.'], $payload);
        $this->assertSame('Recovered.', $result->text);
    }

    public function test_iteration_cap_forces_a_text_answer_with_tools_disabled(): void
    {
        $keepsCalling = new LlmResponse(null, [['name' => 'get_number', 'args' => []]]);

        $orchestrator = $this->orchestrator([
            $keepsCalling, $keepsCalling, $keepsCalling, // maxIterations = 3
            new LlmResponse('Forced answer.'),
        ], maxIterations: 3);

        $result = $orchestrator->run([], 'x');

        $this->assertSame('Forced answer.', $result->text);
        $this->assertCount(4, $this->requests);
        $this->assertSame('none', end($this->requests)->toolMode);
        $this->assertCount(3, $result->citations);
    }

    public function test_deterministic_fallback_when_even_the_forced_call_is_empty(): void
    {
        $keepsCalling = new LlmResponse(null, [['name' => 'get_number', 'args' => []]]);

        $orchestrator = $this->orchestrator([
            $keepsCalling, $keepsCalling, $keepsCalling,
            new LlmResponse(null), // forced call yields nothing
        ], maxIterations: 3);

        $result = $orchestrator->run([], 'x');

        $this->assertStringContainsString('get_number', $result->text);
        $this->assertNotSame('', $result->text);
    }

    public function test_raw_provider_parts_are_echoed_verbatim(): void
    {
        $rawParts = [
            ['functionCall' => ['name' => 'get_number', 'args' => []], 'thoughtSignature' => 'sig-123'],
        ];

        $orchestrator = $this->orchestrator([
            new LlmResponse(null, [['name' => 'get_number', 'args' => []]], $rawParts),
            new LlmResponse('Done.'),
        ]);

        $orchestrator->run([], 'x');

        $assistantTurn = $this->requests[1]->turns[1];
        $this->assertSame([['raw' => $rawParts[0]]], $assistantTurn->parts);
    }

    private function orchestrator(array $scripted, int $maxIterations = 5): ChatOrchestrator
    {
        $this->requests = [];

        $client = new class($scripted, $this->requests) implements LlmClientInterface
        {
            /** @param list<LlmResponse> $scripted */
            public function __construct(
                private array $scripted,
                /** @var list<LlmRequest> */
                private array &$requests,
            ) {}

            public function generate(LlmRequest $request): LlmResponse
            {
                $this->requests[] = $request;

                return array_shift($this->scripted)
                    ?? throw new RuntimeException('Fake LLM ran out of scripted responses.');
            }
        };

        $registry = new ToolRegistry([
            new Tool('get_number', 'Returns a number.', [], static fn (array $args): array => ['number' => 42]),
            new Tool('needs_id', 'Needs a product id.', [
                'type' => 'object',
                'properties' => ['product_id' => ['type' => 'integer']],
                'required' => ['product_id'],
            ], static fn (array $args): array => ['ok' => true]),
            new Tool('explodes', 'Always fails.', [], static function (array $args): array {
                throw new RuntimeException('boom');
            }),
        ]);

        return new ChatOrchestrator($client, $registry, $maxIterations);
    }
}
