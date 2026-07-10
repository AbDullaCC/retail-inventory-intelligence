<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Llm;

use App\Modules\Chatbot\DTOs\LlmMessage;
use App\Modules\Chatbot\DTOs\LlmPart;
use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Exceptions\ChatbotServiceUnavailableException;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gemini implementation of the LLM client (free tier, raw HTTP via the Http
 * facade — mirrors ForecastRunner). Translates the provider-agnostic
 * {@see LlmRequest} into Gemini's generateContent payload and parses the
 * response back into a provider-agnostic {@see LlmResponse}.
 *
 * Gemini specifics handled here (kept out of the orchestrator):
 *  - roles are only `user` and `model`; persisted `assistant` → `model`, and
 *    `functionResponse` parts ride back under the `user` role.
 *  - `functionResponse` parts must be `{name, response: {…}}` (an object).
 *  - `responseMimeType: application/json` is NOT set — it breaks function
 *    calling in some SDK versions.
 *  - 429 (rate limit) is retried with exponential backoff before surfacing 503.
 */
final class GeminiLlmClient implements LlmClientInterface
{
    private const MAX_ATTEMPTS = 3;

    /**
     * @param  array{api_key: string, model: string, base_url: string, timeout: int}  $settings
     */
    public function __construct(
        private readonly array $settings,
        private readonly int $maxTokens,
        private readonly float $temperature,
    ) {}

    private function apiKey(): string
    {
        return (string) ($this->settings['api_key'] ?? '');
    }

    private function model(): string
    {
        return (string) ($this->settings['model'] ?? 'gemini-3.5-flash');
    }

    private function baseUrl(): string
    {
        return (string) ($this->settings['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta');
    }

    private function timeout(): int
    {
        return (int) ($this->settings['timeout'] ?? 30);
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        $payload = $this->payload($request);
        $url = $this->endpoint(':generateContent');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->timeout($this->timeout())
                    ->withHeader('x-goog-api-key', $this->apiKey())
                    ->acceptJson()
                    ->asJson()
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                throw ChatbotServiceUnavailableException::fromGemini(
                    'Could not reach the Gemini API: '.$e->getMessage(),
                    503,
                    $e,
                );
            }

            // Transient rate-limit: back off and retry (up to MAX_ATTEMPTS).
            if ($response->status() === 429 && $attempt < self::MAX_ATTEMPTS) {
                usleep($attempt * 1_000_000); // 1s, 2s backoff
                continue;
            }

            // Any non-2xx (after retries are exhausted) → surface Gemini's own
            // error message verbatim so the user sees the real cause ("This
            // model is currently experiencing high demand", "API key not
            // valid", "models/x is not found", …) instead of a generic string.
            if (! $response->successful()) {
                throw ChatbotServiceUnavailableException::fromGemini(
                    $this->errorMessage($response),
                    503,
                );
            }

            return $this->parse($response->json() ?? []);
        }

        // Unreachable: the loop returns or throws on every path.
        throw ChatbotServiceUnavailableException::fromGemini(
            'Gemini did not return a response after retries.',
            503,
        );
    }

    public function stream(LlmRequest $request): \Generator
    {
        unset($request);

        // SSE streaming lands in M5.
        throw new RuntimeException('Streaming is not implemented until M5.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LlmRequest $request): array
    {
        $contents = array_map(fn (LlmMessage $m) => $this->content($m), $request->messages);

        $payload = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ];

        if ($request->tools !== []) {
            $payload['tools'] = [
                ['functionDeclarations' => array_map(
                    static fn (array $t): array => [
                        'name' => $t['name'],
                        'description' => $t['description'],
                        'parameters' => self::geminiParameters($t['parameters']),
                    ],
                    $request->tools,
                )],
            ];
        }

        // 'none' disables function calling — forces a text answer for the
        // final fallback call at the iteration cap. Emitted independently of the
        // tools block so an explicit "no tools" intent always reaches the model.
        if ($request->toolMode === 'none') {
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'NONE']];
        }

        return $payload;
    }

    /**
     * @return array{role: string, parts: list<array<string, mixed>>}
     */
    private function content(LlmMessage $message): array
    {
        // Gemini only knows `user` and `model`; function responses must travel
        // back under the `user` role.
        $role = $message->role === 'assistant' ? 'model' : 'user';

        $parts = array_map(static function (LlmPart $part): array {
            if ($part->raw !== null) {
                // Provider-detail passthrough (e.g. Gemini 3.x functionCall
                // parts carrying a thoughtSignature that must travel with the
                // call on the next request). Normalize an empty `args` list to
                // an object: the API returns `args: []` for a no-arg call, but
                // rejects the same empty list on echo ("Proto field is not
                // repeating, cannot start list") — it wants a struct (`{}`).
                return self::normalizeRawPart($part->raw);
            }

            if ($part->text !== null) {
                return ['text' => $part->text];
            }

            if ($part->functionCall !== null) {
                return ['functionCall' => $part->functionCall];
            }

            return ['functionResponse' => $part->functionResponse];
        }, $message->parts);

        return ['role' => $role, 'parts' => $parts];
    }

    /**
     * Gemini's function-declaration schema is a *restricted* subset of JSON
     * Schema. It rejects several standard keys with HTTP 400 "Unknown name",
     * notably `additionalProperties`, `$schema`, and `$defs`/`definitions`.
     * Strip those keys recursively (the local {@see ToolArgsValidator} still
     * sees them on the original schema) so only Gemini-accepted fields are
     * sent. This translation belongs at the provider boundary, not on the
     * tool definitions themselves.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function geminiParameters(array $schema): array
    {
        // Keys Gemini's function-declaration schema does not accept.
        $unsupported = ['additionalProperties', '$schema', '$defs', 'definitions'];

        foreach ($unsupported as $key) {
            unset($schema[$key]);
        }

        // Recurse into `properties` (nested object schemas may carry the same keys).
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $prop) {
                if (is_array($prop)) {
                    $schema['properties'][$name] = self::geminiParameters($prop);
                }
            }
        }

        // Recurse into array `items`.
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::geminiParameters($schema['items']);
        }

        return $schema;
    }

    /**
     * Normalize a raw Gemini part before echoing it back. The API returns
     * `functionCall.args` as `[]` for a no-argument call, but rejects that same
     * empty list on the next request ("Proto field is not repeating, cannot
     * start list") — the proto field is a map, so it wants an empty object.
     * Any other keys (notably the `thoughtSignature` on 3.x functionCall parts)
     * are preserved verbatim.
     *
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function normalizeRawPart(array $part): array
    {
        if (isset($part['functionCall']) && is_array($part['functionCall'])) {
            $args = $part['functionCall']['args'] ?? [];
            if ($args === []) {
                $part['functionCall']['args'] = new \stdClass;
            }
        }

        return $part;
    }

    /**
     * Extract Gemini's own human-readable error message from a failed
     * response, e.g. "This model is currently experiencing high demand." Falls
     * back to the raw body so the cause is never hidden behind a generic string.
     */
    private function errorMessage(Response $response): string
    {
        $body = $response->json() ?? [];

        if (isset($body['error']['message']) && is_string($body['error']['message']) && $body['error']['message'] !== '') {
            $message = $body['error']['message'];
        } else {
            $message = trim($response->body()) !== ''
                ? $response->body()
                : sprintf('Gemini returned status %d with no body.', $response->status());
        }

        // Prefix the status so the surfaced text is self-explanatory.
        return sprintf('Gemini API error (HTTP %d): %s', $response->status(), $message);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function parse(array $json): LlmResponse
    {
        $candidate = $json['candidates'][0] ?? [];
        $content = $candidate['content'] ?? ['parts' => []];
        $parts = $content['parts'] ?? [];

        $text = null;
        $functionCalls = [];
        // Preserve the raw parts so the orchestrator can echo the model's own
        // turn back verbatim — Gemini 3.x "thinking" parts carry a
        // thoughtSignature that MUST accompany functionCall parts on the next
        // request, or the API returns HTTP 400 "missing a thought_signature".
        $rawParts = array_values($parts);

        foreach ($parts as $part) {
            // Thought parts look like {text, thought: true, thoughtSignature} —
            // never treat thought text as the answer text.
            if (isset($part['text']) && ! ($part['thought'] ?? false)) {
                $text = ($text ?? '').$part['text'];
            }

            if (isset($part['functionCall']['name'])) {
                $functionCalls[] = [
                    'name' => $part['functionCall']['name'],
                    'args' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        return new LlmResponse(
            text: $text,
            functionCalls: $functionCalls,
            finishReason: (string) ($candidate['finishReason'] ?? 'STOP'),
            rawParts: $rawParts,
        );
    }

    private function endpoint(string $action): string
    {
        return sprintf('/models/%s%s', $this->model(), $action);
    }
}
