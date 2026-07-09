<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

/**
 * Produces the short, human-readable `result_summary` stored alongside each
 * recorded tool call (for the UI's "cited sources"). The summary describes the
 * shape of the result, not its full contents — the LLM already received the
 * truncated payload; this is for the human reader.
 */
final class ToolResultSummary
{
    /**
     * @param  array<string, mixed>  $result
     */
    public static function summarise(string $toolName, array $result): string
    {
        if (isset($result['error']) && is_string($result['error'])) {
            return ucfirst($result['error']);
        }

        if (isset($result['items']) && is_array($result['items'])) {
            $total = $result['total'] ?? count($result['items']);
            $truncated = ($result['truncated'] ?? false) ? ' (truncated)' : '';

            return sprintf('%s returned %d items%s.', $toolName, $total, $truncated);
        }

        // Object-shaped results (a single product, the store overview, …).
        return sprintf('%s returned %d fields.', $toolName, count(array_keys($result)));
    }
}
