<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Product\Services\Contracts\ProductServiceInterface;

/**
 * `search_products` — the paginated product catalogue. Reuses
 * ProductFilterData::fromArray() so sanitisation (whitelisted sort, clamped
 * paging) is not re-implemented here. Page data + meta are returned truncated.
 */
final class SearchProductsTool
{
    public function __construct(
        private readonly ProductServiceInterface $products,
        private readonly int $maxItems,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'search_products',
            description: 'Search and list products from the catalogue. Supports `search` (name/SKU), `category_id`, `low_stock` (bool), `is_active` (bool), `sort_by` (name/sku/price/quantity/reorder_level/created_at/updated_at), `sort_dir` (asc/desc), `per_page` (1-100), `page`. Returns the current page of products plus pagination meta. Use to find a product id by name/SKU before calling product-specific tools.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string'],
                    'category_id' => ['type' => 'integer'],
                    'low_stock' => ['type' => 'boolean'],
                    'is_active' => ['type' => 'boolean'],
                    'sort_by' => ['type' => 'string', 'enum' => ['name', 'sku', 'price', 'quantity', 'reorder_level', 'created_at', 'updated_at']],
                    'sort_dir' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                ],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                $page = $this->products->paginate(ProductFilterData::fromArray($args));
                $payload = $page->toArray();
                $truncated = ToolResultTruncator::truncate($payload['data'], $this->maxItems);

                return [
                    'products' => $truncated['items'],
                    'total' => $truncated['total'],
                    'returned' => count($truncated['items']),
                    'truncated' => $truncated['truncated'],
                    'meta' => $payload['meta'],
                ];
            },
        );
    }
}
