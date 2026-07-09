<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Chatbot\Services\Contracts\ChatbotToolRegistryInterface;
use RuntimeException;

/**
 * The fixed registry of read-only tools. Built once from the injected tool
 * instances (each closes over its resolved service). The set is immutable after
 * construction — no runtime registration, which is part of the read-only
 * guarantee. A later tool with the same name overrides the earlier.
 */
final class ChatbotToolRegistry implements ChatbotToolRegistryInterface
{
    /** @var array<string, ChatbotTool> */
    private array $tools = [];

    /**
     * @param  iterable<ChatbotTool>  $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    public function all(): array
    {
        return $this->tools;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ChatbotTool
    {
        return $this->tools[$name] ?? throw new RuntimeException(sprintf('Unknown chatbot tool: %s', $name));
    }
}
