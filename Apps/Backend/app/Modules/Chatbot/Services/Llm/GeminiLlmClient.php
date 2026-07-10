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
use Illuminate\Http\Client\RequestException;
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
                throw ChatbotServiceUnavailableException::gemini($e);
            }

            if ($response->status() === 429 && $attempt < self::MAX_ATTEMPTS) {
                usleep($attempt * 1_000_000); // 1s, 2s backoff
                continue;
            }

            if ($response->status() === 429) {
                throw ChatbotServiceUnavailableException::gemini();
            }

            try {
                $response->throw();
            } catch (RequestException $e) {
                throw ChatbotServiceUnavailableException::gemini($e);
            }

            return $this->parse($response->json() ?? []);
        }

        // Unreachable: the loop returns or throws on every path.
        throw ChatbotServiceUnavailableException::gemini();
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
                        'parameters' => $t['parameters'],
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
     * @param  array<string, mixed>  $json
     */
    private function parse(array $json): LlmResponse
    {
        $candidate = $json['candidates'][0] ?? [];
        $content = $candidate['content'] ?? ['parts' => []];
        $parts = $content['parts'] ?? [];

        $text = null;
        $functionCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
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
        );
    }

    private function endpoint(string $action): string
    {
        return sprintf('/models/%s%s', $this->model(), $action);
    }
}
