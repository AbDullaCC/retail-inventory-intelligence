<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * Minimal JSON-schema check for tool arguments coming from the LLM. Guards the
 * handlers against junk (wrong types, out-of-range, unknown enum values)
 * without pulling in a full validator library. Deliberately lenient where the
 * model is sloppy in practice: numeric strings pass integer/number checks, and
 * unknown properties are tolerated (handlers ignore them).
 */
final class ToolArgsValidator
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $schema
     */
    public static function valid(array $args, array $schema): bool
    {
        foreach ((array) ($schema['required'] ?? []) as $required) {
            if (! array_key_exists($required, $args)) {
                return false;
            }
        }

        $properties = $schema['properties'] ?? [];
        if (! is_array($properties)) {
            return true;
        }

        foreach ($properties as $name => $rules) {
            if (! array_key_exists($name, $args) || ! is_array($rules)) {
                continue;
            }

            if (! self::validValue($args[$name], $rules)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function validValue(mixed $value, array $rules): bool
    {
        $type = $rules['type'] ?? null;

        $numeric = is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));

        $typeOk = match ($type) {
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'number' => $numeric,
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            default => true,
        };

        if (! $typeOk) {
            return false;
        }

        if (isset($rules['enum']) && is_array($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            return false;
        }

        if ($numeric) {
            $number = (float) $value;

            if (isset($rules['minimum']) && $number < (float) $rules['minimum']) {
                return false;
            }
            if (isset($rules['maximum']) && $number > (float) $rules['maximum']) {
                return false;
            }
        }

        return true;
    }
}
