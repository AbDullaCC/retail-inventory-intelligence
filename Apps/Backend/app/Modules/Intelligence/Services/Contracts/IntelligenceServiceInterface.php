<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Services\Contracts;

use App\Modules\Intelligence\DTOs\RecommendationDTO;
use App\Modules\Intelligence\DTOs\RecommendationsSummaryDTO;

interface IntelligenceServiceInterface
{
    /** Recommendations for every product, plus headline aggregates. */
    public function recommendations(): RecommendationsSummaryDTO;

    /** Recommendation for a single product (404 if it does not exist). */
    public function forProduct(int $productId): RecommendationDTO;
}
