<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\DTOs\LlmTurn;
use App\Modules\Chatbot\Exceptions\ChatUnavailableException;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Tools\ToolArgsValidator;
use App\Modules\Chatbot\Services\Tools\ToolRegistry;
use App\Modules\Chatbot\Services\Tools\ToolResultTruncator;
use App\Modules\Chatbot\Support\SystemPrompt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * The tool-calling loop. Sends history + the new user message to the LLM;
 * while the model requests tools, resolves them through the registry and
 * feeds the results back — up to `maxIterations` rounds. Tool failures are
 * returned to the MODEL as error payloads, never thrown: a wrong product id
 * must produce "I couldn't find that product", not an HTTP 404.
 *
 * At the cap, one final call with tools disabled forces a text answer; if
 * even that yields nothing, a deterministic sentence is synthesised so the
 * user is never left with an empty reply.
 */
final class ChatOrchestrator
{
    public function __construct(
        private readonly LlmClientInterface $client,
        private readonly ToolRegistry $registry,
        private readonly int $maxIterations,
    ) {}

    /**
     * @param  list<LlmTurn>  $history
     */
    public function run(array $history, string $message): ChatResult
    {
        $system = SystemPrompt::text();
        $tools = $this->registry->declarations();

        $turns = [...$history, LlmTurn::user($message)];
        $citations = [];

        for ($iteration = 0; $iteration < $this->maxIterations; $iteration++) {
            $response = $this->client->generate(new LlmRequest($system, $turns, $tools));

            if (! $response->wantsTools()) {
                return new ChatResult(trim($response->text ?? ''), $citations);
            }

            $results = [];
            foreach ($response->toolCalls as $call) {
                $payload = $this->execute($call['name'], $call['args']);
                $results[] = ['name' => $call['name'], 'payload' => $payload];
                $citations[] = [
                    'name' => $call['name'],
                    'summary' => ToolResultTruncator::summarise($call['name'], $payload),
                ];
            }

            // The model's own tool-call turn goes back verbatim (raw parts keep
            // Gemini 3.x thought signatures attached), then the results.
            $turns[] = $this->assistantToolTurn($response);
            $turns[] = LlmTurn::toolResults($results);
        }

        // Still calling tools at the cap — disallow tools and force an answer.
        $forced = $this->client->generate(new LlmRequest($system, $turns, $tools, 'none'));

        if ($forced->text !== null && trim($forced->text) !== '') {
            return new ChatResult(trim($forced->text), $citations);
        }

        return new ChatResult($this->lastResort($citations), $citations);
    }

    /**
     * Run one tool call defensively: validation failures and handler
     * exceptions become error payloads the model can react to.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function execute(string $name, array $args): array
    {
        $tool = $this->registry->find($name);

        if ($tool === null) {
            return ['error' => sprintf('Unknown tool "%s".', $name)];
        }

        if (! ToolArgsValidator::valid($args, $tool->parameters)) {
            return ['error' => 'Invalid arguments for this tool.'];
        }

        try {
            return $tool->run($args);
        } catch (ModelNotFoundException) {
            return ['error' => 'No matching record — the product id may be wrong. Use find_product to resolve names to ids.'];
        } catch (ChatUnavailableException $e) {
            throw $e; // provider problems must surface, not be fed to the model
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'The tool failed unexpectedly.'];
        }
    }

    private function assistantToolTurn(LlmResponse $response): LlmTurn
    {
        if ($response->rawParts !== []) {
            return new LlmTurn('assistant', array_map(
                static fn (array $part): array => ['raw' => $part],
                $response->rawParts,
            ));
        }

        return new LlmTurn('assistant', array_map(
            static fn (array $call): array => ['call' => $call],
            $response->toolCalls,
        ));
    }

    /**
     * @param  list<array{name: string, summary: string}>  $citations
     */
    private function lastResort(array $citations): string
    {
        if ($citations === []) {
            return 'I could not work out an answer to that. Try rephrasing the question, or ask about stock, forecasts, or reorder recommendations.';
        }

        $last = end($citations);

        return sprintf(
            'I gathered data (%s) but could not compose a full answer. The last lookup returned: %s. Try asking a more specific question.',
            implode(', ', array_column($citations, 'name')),
            $last['summary'],
        );
    }
}
