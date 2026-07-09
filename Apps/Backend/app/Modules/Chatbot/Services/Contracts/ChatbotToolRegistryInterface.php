<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Contracts;

use App\Modules\Chatbot\Services\Tools\ChatbotTool;

/**
 * The fixed registry of read-only tools the LLM may invoke. The set is bound at
 * provider time and never mutated — there is no runtime registration, which is
 * part of the read-only guarantee.
 */
interface ChatbotToolRegistryInterface
{
    /**
     * @return array<string, ChatbotTool>
     */
    public function all(): array;

    public function has(string $name): bool;

    public function get(string $name): ChatbotTool;
}
