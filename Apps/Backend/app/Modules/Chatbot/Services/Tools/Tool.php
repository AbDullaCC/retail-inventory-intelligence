<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use Closure;

/**
 * A single read-only capability exposed to the LLM: a name, a description the
 * model reads to decide when to call it, a JSON-schema for its arguments, and
 * the handler that executes it against the existing service layer.
 */
final class Tool
{
    /**
     * @param  array<string, mixed>  $parameters  JSON schema for the arguments
     * @param  Closure(array<string, mixed>): array<string, mixed>  $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        private readonly Closure $handler,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function run(array $args): array
    {
        return ($this->handler)($args);
    }
}
