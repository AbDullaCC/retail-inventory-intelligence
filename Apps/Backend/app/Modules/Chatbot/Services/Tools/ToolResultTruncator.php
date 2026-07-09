<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * Caps a list-returning tool result at the configured limit and records how
 * much was dropped, so the LLM never sees a 250-product payload that would blow
 * the token budget — only a truncated slice plus a `{truncated, total}` note.
 *
 * The full payload is never stored or sent; the UI's "cited sources" use the
 * tool-call `result_summary`, not the raw data.
 */
final class ToolResultTruncator
{
    /**
     * @param  list<mixed>  $items
     * @return array{items: list<mixed>, total: int, truncated: bool}
     */
    public static function truncate(array $items, int $limit): array
    {
        $total = count($items);

        return [
            'items' => $total > $limit ? array_slice($items, 0, $limit) : $items,
            'total' => $total,
            'truncated' => $total > $limit,
        ];
    }
}
