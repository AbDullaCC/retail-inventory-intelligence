<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Contracts;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;
use App\Modules\Chatbot\Exceptions\ChatUnavailableException;

/**
 * Provider boundary for the AI assistant. Gemini today; anything with
 * function calling tomorrow — the orchestrator never sees wire formats.
 */
interface LlmClientInterface
{
    /**
     * @throws ChatUnavailableException when the provider is unreachable or errors
     */
    public function generate(LlmRequest $request): LlmResponse;
}
