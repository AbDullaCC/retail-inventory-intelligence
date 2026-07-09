<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Product\Services\Contracts\ProductServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * `get_product` — a single product's catalogue record (id, sku, name, price,
 * cost, quantity, reorder level, low-stock flag, category). A missing product
 * returns a structured `{error}`.
 */
final class GetProductTool
{
    public function __construct(
        private readonly ProductServiceInterface $products,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_product',
            description: 'One product\'s catalogue details: sku, name, description, price, cost, quantity on hand, reorder level, low-stock flag, active flag, and category. Use when the user asks about a specific product by id and you need its stock-level details (not the forecast/recommendation).',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
                'required' => ['product_id'],
                'additionalProperties' => false,
            ],
            handler: function (array $args): array {
                try {
                    return $this->products->find((int) $args['product_id'])->toArray();
                } catch (ModelNotFoundException) {
                    return ['error' => 'product not found'];
                }
            },
        );
    }
}
