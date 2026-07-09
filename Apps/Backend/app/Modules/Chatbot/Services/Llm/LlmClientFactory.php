<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Llm;

use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use RuntimeException;

/**
 * Resolves the configured LLM provider implementation. Swapping to Groq/Ollama/a
 * paid model is a new client class + a branch here (or a provider-keyed map).
 * Bound in the ChatbotServiceProvider so call sites depend only on the interface.
 */
final class LlmClientFactory
{
    public function make(): LlmClientInterface
    {
        $config = config('services.chatbot');
        $provider = (string) ($config['provider'] ?? 'gemini');

        return match ($provider) {
            'gemini' => $this->gemini((array) ($config['gemini'] ?? []), $config),
            default => throw new RuntimeException(sprintf('Unknown chatbot LLM provider: %s', $provider)),
        };
    }

    /**
     * @param  array<string, mixed>  $gemini
     * @param  array<string, mixed>  $config
     */
    private function gemini(array $gemini, array $config): GeminiLlmClient
    {
        return new GeminiLlmClient(
            settings: [
                'api_key' => (string) ($gemini['api_key'] ?? ''),
                'model' => (string) ($gemini['model'] ?? 'gemini-3.5-flash'),
                'base_url' => (string) ($gemini['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'),
                'timeout' => (int) ($gemini['timeout'] ?? 30),
            ],
            maxTokens: (int) ($config['max_tokens'] ?? 2048),
            temperature: (float) ($config['temperature'] ?? 0.2),
        );
    }
}
