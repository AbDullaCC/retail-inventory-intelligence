<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services;

use App\Modules\Chatbot\DTOs\LlmMessage;
use App\Modules\Chatbot\DTOs\LlmPart;
use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Services\Contracts\ChatbotToolRegistryInterface;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Tools\ToolArgsValidator;
use App\Modules\Chatbot\Services\Tools\ToolResultSummary;
use App\Modules\Chatbot\Support\SystemPromptBuilder;

/**
 * The tool-calling loop. Receives the recent history + a new user message,
 * builds the LLM request (system prompt + tool declarations + history window),
 * calls the LLM, resolves any function calls through the registry, wraps each
 * result as a functionResponse, and re-calls — up to max_tool_iterations.
 *
 * At the cap, one final call with function calling disabled (tool_mode: none)
 * forces a text answer from the accumulated context; only if that fails too is
 * a deterministic one-liner synthesized from the last tool result.
 */
final class ChatbotOrchestrator
{
    /**
     * @param  list<LlmMessage>  $history
     */
    public function run(array $history, string $message): OrchestratorResult
    {
        $system = $this->promptBuilder->build();
        $tools = $this->toolDeclarations();

        $messages = $history;
        $messages[] = LlmMessage::text('user', $message);

        $recorded = [];

        for ($iteration = 1; $iteration <= $this->maxIterations; $iteration++) {
            $response = $this->client->generate(new LlmRequest($system, $messages, $tools, 'auto'));

            if (! $response->wantsTools()) {
                return new OrchestratorResult($response->text ?? '', $recorded);
            }

            $responseParts = [];
            foreach ($response->functionCalls as $call) {
                $name = $call['name'];
                $args = $call['args'];

                if (! $this->registry->has($name)) {
                    $responseParts[] = LlmPart::functionResponse($name, ['error' => 'unknown tool']);
                    $recorded[] = [
                        'name' => $name,
                        'args' => $args,
                        'result_summary' => 'unknown tool',
                    ];
                    continue;
                }

                $tool = $this->registry->get($name);

                if (! ToolArgsValidator::valid($args, $tool->parameters)) {
                    $responseParts[] = LlmPart::functionResponse($name, ['error' => 'invalid arguments']);
                    $recorded[] = [
                        'name' => $name,
                        'args' => $args,
                        'result_summary' => 'invalid arguments',
                    ];
                    continue;
                }

                $result = $tool->handle($args);
                $responseParts[] = LlmPart::functionResponse($name, $result);
                $recorded[] = [
                    'name' => $name,
                    'args' => $args,
                    'result_summary' => ToolResultSummary::summarise($name, $result),
                ];
            }

            // Echo the model's tool-call turn back as an assistant message, then
            // the function responses under a user message (Gemini's role rule).
            // When the provider supplied raw parts (Gemini 3.x thought parts
            // carrying a thoughtSignature that MUST travel with the
            // functionCall), echo those verbatim — reconstructing only the
            // functionCalls would drop the signature and the API 400s.
            $messages[] = $this->assistantFunctionCallMessage($response);
            $messages[] = new LlmMessage('user', $responseParts);
        }

        // Reached the iteration cap still calling tools: force a text answer
        // with function calling disabled — no new tool calls are possible.
        $forced = $this->client->generate(new LlmRequest($system, $messages, $tools, 'none'));

        if ($forced->text !== null && trim($forced->text) !== '') {
            return new OrchestratorResult($forced->text, $recorded);
        }

        // Deterministic last resort from the last tool result.
        return new OrchestratorResult($this->fallbackText($recorded), $recorded);
    }

    public function __construct(
        private readonly LlmClientInterface $client,
        private readonly ChatbotToolRegistryInterface $registry,
        private readonly SystemPromptBuilder $promptBuilder,
        private readonly int $maxIterations,
    ) {}

    /**
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    private function toolDeclarations(): array
    {
        $decls = [];

        foreach ($this->registry->all() as $tool) {
            $decls[] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->parameters,
            ];
        }

        return $decls;
    }

    /**
     * Reconstruct the model's tool-call turn as an assistant message so the
     * call/result pairing is preserved in the conversation history. When the
     * provider supplied raw parts, echo those verbatim (Gemini 3.x thought
     * parts + their thoughtSignature must accompany the functionCall on the
     * next request); otherwise fall back to rebuilding from the parsed calls.
     */
    private function assistantFunctionCallMessage(LlmResponse $response): LlmMessage
    {
        if ($response->rawParts !== []) {
            return new LlmMessage(
                'assistant',
                array_map(static fn (array $raw): LlmPart => LlmPart::raw($raw), $response->rawParts),
            );
        }

        $parts = [];
        foreach ($response->functionCalls as $call) {
            $parts[] = LlmPart::functionCall($call['name'], $call['args']);
        }

        return new LlmMessage('assistant', $parts);
    }

    /**
     * @param  list<array{name: string, args: array<string, mixed>, result_summary: string}>  $recorded
     */
    private function fallbackText(array $recorded): string
    {
        if ($recorded === []) {
            return 'I wasn\'t able to retrieve the information needed to answer that. Please try rephrasing your question.';
        }

        $names = array_map(static fn (array $c): string => $c['name'], $recorded);
        $summary = end($recorded)['result_summary'] ?? '';

        return sprintf(
            'Based on the data I retrieved (tools: %s), I couldn\'t produce a complete answer. %s',
            implode(', ', $names),
            $summary,
        );
    }
}
