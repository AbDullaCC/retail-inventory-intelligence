<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * The assistant's fixed tool set, keyed by name. Immutable after construction
 * — no runtime registration, which is part of the read-only guarantee.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    public function find(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Wire-format declarations for the LLM request.
     *
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function declarations(): array
    {
        return array_values(array_map(
            static fn (Tool $tool): array => [
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->parameters,
            ],
            $this->tools,
        ));
    }
}
