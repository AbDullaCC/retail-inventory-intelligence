<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use Illuminate\Support\Arr;

/**
 * `find_product` — resolves a product name/SKU fragment to concrete products
 * (id, stock, price). The system prompt tells the model to call this first
 * whenever the user names a product, then use the id-based tools.
 *
 * Matching is two-tier: an exact substring search first; if that finds
 * nothing (usually a typo — the database has no fuzzy matching), each word of
 * the query is searched separately and the union returned, so "rabit nite
 * light" still surfaces "Rabbit Night Light" via the one word that survived.
 */
final class FindProductTool
{
    private const MATCH_LIMIT = 8;

    /** Words shorter than this are too generic to search alone. */
    private const MIN_WORD_LENGTH = 3;

    public function __construct(
        private readonly ProductServiceInterface $products,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'find_product',
            description: 'Search the catalogue by product name or SKU (partial matches allowed). Returns up to '.self::MATCH_LIMIT.' matches with product id, current stock, price and status. ALWAYS use this first to resolve a product mentioned by name, then pass its product_id to the other tools. Falls back to per-word matching when the exact phrase finds nothing.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Name or SKU fragment, e.g. "alarm clock" or "SHOPIFY-1".',
                    ],
                ],
                'required' => ['query'],
            ],
            handler: function (array $args): array {
                $query = trim((string) $args['query']);

                $matches = $this->search($query);
                $note = null;

                if ($matches === [] && str_contains($query, ' ')) {
                    $matches = $this->searchByWords($query);
                    if ($matches !== []) {
                        $note = 'No product matched the exact phrase — these matched individual words of it. Pick the closest or ask the user which one they meant.';
                    }
                }

                $result = [
                    'matches' => $matches,
                    'total' => count($matches),
                ];

                if ($note !== null) {
                    $result['note'] = $note;
                }

                return $result;
            },
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $term): array
    {
        if ($term === '') {
            return [];
        }

        $page = $this->products->paginate(ProductFilterData::fromArray([
            'search' => $term,
            'per_page' => self::MATCH_LIMIT,
        ]));

        return array_map(static function ($dto): array {
            $row = is_array($dto) ? $dto : $dto->toArray();

            return [
                ...Arr::only($row, ['id', 'sku', 'name', 'quantity', 'price', 'is_active', 'is_low_stock']),
                'category' => $row['category']['name'] ?? null,
            ];
        }, $page->items);
    }

    /**
     * Union of per-word matches, deduplicated, capped. A typo usually breaks
     * one word, not all of them.
     *
     * @return list<array<string, mixed>>
     */
    private function searchByWords(string $query): array
    {
        $words = array_filter(
            preg_split('/[^\pL\pN]+/u', $query) ?: [],
            static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH,
        );

        $byId = [];
        foreach ($words as $word) {
            foreach ($this->search($word) as $row) {
                $byId[$row['id']] ??= $row;
            }

            if (count($byId) >= self::MATCH_LIMIT) {
                break;
            }
        }

        return array_slice(array_values($byId), 0, self::MATCH_LIMIT);
    }
}
