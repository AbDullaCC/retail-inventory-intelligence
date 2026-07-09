<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Contracts;

use App\Modules\Chatbot\DTOs\LlmRequest;
use App\Modules\Chatbot\DTOs\LlmResponse;

/**
 * A single LLM provider behind the chatbot. Implementations translate the
 * provider-agnostic {@see LlmRequest} into their wire format and parse back a
 * provider-agnostic {@see LlmResponse}. Swapping providers is a config + new
 * class change (see config/services.php → chatbot.provider).
 */
interface LlmClientInterface
{
    /** Non-streaming generation (v1). */
    public function generate(LlmRequest $request): LlmResponse;

    /**
     * Streaming generation (v2 SSE). Yields text deltas as they arrive.
     * Not implemented until M5 — throws LogicException.
     *
     * @return \Generator<int, string, void, void>
     */
    public function stream(LlmRequest $request): \Generator;
}
