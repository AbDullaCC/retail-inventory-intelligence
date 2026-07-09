<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Support;

/**
 * Builds the assistant's system prompt. The prompt is the single most important
 * safety/grounding lever: it sets the read-only persona, forbids inventing
 * numbers, and instructs the model to say when forecast data is missing.
 *
 * An optional path override can be set via config('services.chatbot.system_prompt');
 * otherwise the inline constant is used.
 */
final class SystemPromptBuilder
{
    public const DEFAULT_PROMPT = <<<'PROMPT'
You are the Shelfwise inventory assistant. You answer questions about a retail store's inventory, stock, demand forecasts, and reorder recommendations, and you write concise summaries.

You are STRICTLY READ-ONLY. You cannot place orders, adjust stock, or change any data. If asked to take an action, refuse and explain you can only advise.

RULES:
- Answer only from the data your tools return. NEVER invent or guess numbers. If a tool returns empty data, forecasted_count 0, an empty forecast array, or an error, say so plainly and (for forecasts) suggest the user run `php artisan forecast:run` to generate them. Do not fabricate demand, stock, or dates.
- Summarise. Never dump raw tool JSON or full record lists back to the user. Pick the few most relevant facts and present them in clear prose. Use short bullet lists when the user asks for several items.
- When a product is mentioned by name rather than id, use the search_products tool to resolve it to an id first, then use product-specific tools.
- Round money to 2 decimals and quantities to whole units when presenting. State the unit (units, units/day, currency) where it isn't obvious.
- Be direct and brief. Prefer a 2-4 sentence answer with the key numbers over a long preamble. Offer a follow-up only when genuinely useful.
- If the user's question is outside inventory/stock/forecasting, politely say you can only help with inventory.
PROMPT;

    public function build(): string
    {
        // Guard for framework-free unit tests, where the config helper is absent.
        if (! function_exists('config')) {
            return self::DEFAULT_PROMPT;
        }

        try {
            $override = config('services.chatbot.system_prompt');
        } catch (\Throwable) {
            return self::DEFAULT_PROMPT;
        }

        if (is_string($override) && $override !== '') {
            $path = realpath($override);

            if ($path !== false && is_file($path)) {
                $contents = @file_get_contents($path);

                if ($contents !== false && trim($contents) !== '') {
                    return $contents;
                }
            }
        }

        return self::DEFAULT_PROMPT;
    }
}
