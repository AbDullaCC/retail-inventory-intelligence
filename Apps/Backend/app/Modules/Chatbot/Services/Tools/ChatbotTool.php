<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * A read-only tool the LLM may invoke: a name, a description, a JSON Schema for
 * its arguments, and a handler that calls an existing read service and returns
 * an arrayable result. The handler closes over the resolved service.
 *
 * This is an immutable value object — the registry maps name => ChatbotTool at
 * bind time and never mutates it.
 */
final class ChatbotTool
{
    /**
     * @param  array<string, mixed>  $parameters  JSON Schema describing the args.
     * @param  callable(array<string, mixed>): array<string, mixed>  $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly mixed $handler,
    ) {}

    /**
     * Execute the tool against validated args. Returns a plain array (already
     * truncated by the caller's choice; tools cap their own list results).
     * Invokable so a tool can be called directly as `$tool($args)`.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(array $args): array
    {
        return ($this->handler)($args);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        return ($this->handler)($args);
    }
}
