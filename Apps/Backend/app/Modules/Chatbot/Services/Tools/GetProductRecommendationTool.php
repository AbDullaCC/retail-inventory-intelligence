<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * `get_product_recommendation` — a single product's full recommendation
 * (verdict, reorder qty, stockout projection, reasoning). A missing product
 * returns a structured `{error}`, never throws.
 */
final class GetProductRecommendationTool
{
    public function __construct(
        private readonly IntelligenceServiceInterface $intelligence,
    ) {}

    public function build(): ChatbotTool
    {
        return new ChatbotTool(
            name: 'get_product_recommendation',
            description: 'The full inventory recommendation for one product: verdict, current stock, sales velocity, days of stock left, suggested reorder qty, reorder-by date, urgency, stockout risk, projected stockout date, demand trend, and reasoning. Use when the user asks about a specific product id.',
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
                    return $this->intelligence->forProduct((int) $args['product_id'])->toArray();
                } catch (ModelNotFoundException) {
                    return ['error' => 'product not found'];
                }
            },
        );
    }
}
