<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Llm;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\DTOs\LlmTurn;
use App\Modules\Chatbot\Exceptions\ChatUnavailableException;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use stdClass;

/**
 * Gemini implementation of the LLM boundary — raw HTTP through the Http
 * facade (same idiom as ShopifyClient/ForecastRunner; no SDK dependency).
 *
 * Provider quirks, all learned from live testing and kept OUT of the
 * orchestrator:
 *  - roles are only `user`/`model`; tool results ride under `user`.
 *  - function-declaration schemas are a restricted JSON-Schema subset:
 *    `additionalProperties`, `$schema`, `$defs`/`definitions` → HTTP 400.
 *  - 3.x thinking models: thought parts ({text, thought: true}) are never
 *    answer text, and raw response parts must be echoed back verbatim
 *    (thoughtSignature travels with the functionCall).
 *  - `functionCall.args` arrives as [] for no-arg calls but must be echoed as
 *    an OBJECT ({}), or the API rejects it ("Proto field is not repeating").
 *  - `responseMimeType: application/json` must NOT be set (breaks tool calls).
 *  - 429 → short backoff retry; every other failure surfaces Google's own
 *    error.message so the user sees the real cause.
 */
final class GeminiClient implements LlmClientInterface
{
    private const MAX_ATTEMPTS = 3;

    /** Schema keys Gemini's function declarations reject with HTTP 400. */
    private const UNSUPPORTED_SCHEMA_KEYS = ['additionalProperties', '$schema', '$defs', 'definitions'];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $maxTokens,
        private readonly float $temperature,
        private readonly int $retryDelayMs = 1000,
    ) {}

    public function generate(LlmRequest $request): LlmResponse
    {
        if (trim($this->apiKey) === '') {
            throw ChatUnavailableException::notConfigured();
        }

        $url = sprintf('/models/%s:generateContent', $this->model);
        $payload = $this->payload($request);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::baseUrl($this->baseUrl)
                    ->timeout($this->timeout)
                    ->withHeader('x-goog-api-key', $this->apiKey)
                    ->acceptJson()
                    ->asJson()
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                throw new ChatUnavailableException('Could not reach the Gemini API: '.$e->getMessage(), $e);
            }

            if ($response->status() === 429 && $attempt < self::MAX_ATTEMPTS) {
                usleep($attempt * $this->retryDelayMs * 1000); // 1s, then 2s at the default delay

                continue;
            }

            if (! $response->successful()) {
                throw new ChatUnavailableException($this->errorMessage($response));
            }

            return $this->parse($response->json() ?? []);
        }

        throw new ChatUnavailableException('Gemini did not return a response after retries.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LlmRequest $request): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'contents' => array_map(fn (LlmTurn $turn): array => $this->content($turn), $request->turns),
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ];

        if ($request->tools !== []) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(
                    static function (array $tool): array {
                        $declaration = [
                            'name' => $tool['name'],
                            'description' => $tool['description'],
                        ];

                        // A no-argument tool omits `parameters` entirely — an
                        // empty PHP array would serialise to JSON `[]`, which
                        // Gemini rejects (schemas must be objects).
                        if ($tool['parameters'] !== []) {
                            $declaration['parameters'] = self::restrictSchema($tool['parameters']);
                        }

                        return $declaration;
                    },
                    $request->tools,
                ),
            ]];
        }

        if ($request->toolMode === 'none') {
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'NONE']];
        }

        return $payload;
    }

    /**
     * @return array{role: string, parts: list<array<string, mixed>>}
     */
    private function content(LlmTurn $turn): array
    {
        $parts = [];

        foreach ($turn->parts as $part) {
            if (isset($part['raw'])) {
                $parts[] = self::normalizeEchoedPart($part['raw']);
            } elseif (isset($part['text'])) {
                $parts[] = ['text' => $part['text']];
            } elseif (isset($part['call'])) {
                $parts[] = ['functionCall' => [
                    'name' => $part['call']['name'],
                    'args' => $part['call']['args'] === [] ? new stdClass : $part['call']['args'],
                ]];
            } elseif (isset($part['result'])) {
                $parts[] = ['functionResponse' => [
                    'name' => $part['result']['name'],
                    'response' => $part['result']['payload'],
                ]];
            }
        }

        return [
            'role' => $turn->role === 'assistant' ? 'model' : 'user',
            'parts' => $parts,
        ];
    }

    /**
     * Strip JSON-Schema keys Gemini rejects, recursively. Only the wire copy
     * is stripped — local validation still sees the full schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function restrictSchema(array $schema): array
    {
        foreach (self::UNSUPPORTED_SCHEMA_KEYS as $key) {
            unset($schema[$key]);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property)) {
                    $schema['properties'][$name] = self::restrictSchema($property);
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::restrictSchema($schema['items']);
        }

        return $schema;
    }

    /**
     * A raw response part being echoed back: Gemini returns a no-arg
     * functionCall with `args: []` but rejects that same empty LIST on the next
     * request — the proto field is a map, so it must go back as an object.
     *
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function normalizeEchoedPart(array $part): array
    {
        if (isset($part['functionCall']) && is_array($part['functionCall'])) {
            if (($part['functionCall']['args'] ?? null) === []) {
                $part['functionCall']['args'] = new stdClass;
            }
        }

        return $part;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function parse(array $json): LlmResponse
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        $text = null;
        $toolCalls = [];

        foreach ($parts as $part) {
            // Thinking-model thought parts carry text too — never answer text.
            if (isset($part['text']) && ! ($part['thought'] ?? false)) {
                $text = ($text ?? '').$part['text'];
            }

            if (isset($part['functionCall']['name'])) {
                $toolCalls[] = [
                    'name' => (string) $part['functionCall']['name'],
                    'args' => is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [],
                ];
            }
        }

        return new LlmResponse(
            text: $text,
            toolCalls: $toolCalls,
            rawParts: array_values(is_array($parts) ? $parts : []),
        );
    }

    /**
     * Surface Google's own error message ("API key not valid…", "The model is
     * overloaded…") so the failure is self-explanatory in the UI.
     */
    private function errorMessage(Response $response): string
    {
        $body = $response->json() ?? [];
        $message = $body['error']['message'] ?? null;

        if (! is_string($message) || $message === '') {
            $message = trim($response->body()) !== ''
                ? $response->body()
                : 'no response body';
        }

        return sprintf('Gemini API error (HTTP %d): %s', $response->status(), $message);
    }
}
