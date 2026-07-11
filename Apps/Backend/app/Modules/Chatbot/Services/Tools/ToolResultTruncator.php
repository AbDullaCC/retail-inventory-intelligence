<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * Caps list-shaped tool results before they are fed back to the LLM, so a
 * 250-product store never floods the context window. The note (`total`,
 * `truncated`) tells the model it is looking at a slice, not everything.
 */
final class ToolResultTruncator
{
    /**
     * @param  list<mixed>  $items
     * @return array{items: list<mixed>, total: int, truncated: bool}
     */
    public static function cap(array $items, int $limit): array
    {
        $total = count($items);

        return [
            'items' => array_slice($items, 0, max(1, $limit)),
            'total' => $total,
            'truncated' => $total > $limit,
        ];
    }

    /**
     * One-line human summary of a tool result — persisted with the assistant
     * message as its citation record ("sources"), never the raw payload.
     *
     * @param  array<string, mixed>  $result
     */
    public static function summarise(string $name, array $result): string
    {
        if (isset($result['error'])) {
            return sprintf('%s: %s', $name, (string) $result['error']);
        }

        foreach ($result as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $count = count($value);
                $total = isset($result['total']) && is_numeric($result['total']) ? (int) $result['total'] : $count;

                return $total > $count
                    ? sprintf('%s: %d of %d %s', $name, $count, $total, (string) $key)
                    : sprintf('%s: %d %s', $name, $count, (string) $key);
            }
        }

        return sprintf('%s: %d fields', $name, count($result));
    }
}
