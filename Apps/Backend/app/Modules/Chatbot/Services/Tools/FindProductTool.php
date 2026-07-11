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
 */
final class FindProductTool
{
    private const MATCH_LIMIT = 8;

    public function __construct(
        private readonly ProductServiceInterface $products,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'find_product',
            description: 'Search the catalogue by product name or SKU (partial matches allowed). Returns up to '.self::MATCH_LIMIT.' matches with product id, current stock, price and status. ALWAYS use this first to resolve a product mentioned by name, then pass its product_id to the other tools.',
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
                $page = $this->products->paginate(ProductFilterData::fromArray([
                    'search' => (string) $args['query'],
                    'per_page' => self::MATCH_LIMIT,
                ]));

                $matches = array_map(static function ($dto): array {
                    $row = is_array($dto) ? $dto : $dto->toArray();

                    return [
                        ...Arr::only($row, ['id', 'sku', 'name', 'quantity', 'price', 'is_active', 'is_low_stock']),
                        'category' => $row['category']['name'] ?? null,
                    ];
                }, $page->items);

                return [
                    'matches' => $matches,
                    'total' => $page->total,
                    'truncated' => $page->total > count($matches),
                ];
            },
        );
    }
}
