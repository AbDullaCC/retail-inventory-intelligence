<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * Validates LLM-provided tool arguments against a tool's JSON Schema. Checks the
 * subset the chatbot schemas use: required keys, primitive types (with numeric-
 * string coercion for integers — models sometimes emit "7" not 7), enums, and
 * minimum/maximum bounds. Unknown properties are tolerated unless
 * `additionalProperties: false` is set. Invalid args are returned to the model
 * as an error functionResponse so it can self-correct — never thrown.
 */
final class ToolArgsValidator
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $schema
     */
    public static function valid(array $args, array $schema): bool
    {
        $properties = $schema['properties'] ?? [];

        // Required keys must be present.
        foreach ($schema['required'] ?? [] as $required) {
            if (! array_key_exists($required, $args)) {
                return false;
            }
        }

        // Per-property checks. Unknown properties are tolerated unless the
        // schema explicitly forbids them.
        foreach ($properties as $name => $prop) {
            if (! array_key_exists($name, $args)) {
                continue;
            }

            if (! self::valueMatches($args[$name], $prop)) {
                return false;
            }
        }

        if (($schema['additionalProperties'] ?? true) === false) {
            foreach (array_keys($args) as $key) {
                if (! array_key_exists($key, $properties)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $prop
     */
    private static function valueMatches(mixed $value, array $prop): bool
    {
        $type = $prop['type'] ?? null;

        // Coerce numeric strings to integers — models sometimes emit "7".
        if ($type === 'integer' && is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $value = (int) $value;
        }

        $matches = match ($type) {
            'string' => is_string($value)
                && (! isset($prop['enum']) || in_array($value, $prop['enum'], true)),
            'integer' => is_int($value)
                && self::withinBounds($value, $prop),
            'number' => (is_int($value) || is_float($value))
                && self::withinBounds($value, $prop),
            'boolean' => is_bool($value),
            'object' => is_array($value),
            'array' => is_array($value),
            null => true,
            default => true,
        };

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $prop
     */
    private static function withinBounds(int|float $value, array $prop): bool
    {
        if (isset($prop['minimum']) && $value < $prop['minimum']) {
            return false;
        }

        if (isset($prop['maximum']) && $value > $prop['maximum']) {
            return false;
        }

        return true;
    }
}
