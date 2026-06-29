<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Contracts;

use App\Modules\Product\DTOs\ProductData;
use App\Modules\Product\DTOs\ProductDTO;
use App\Modules\Product\DTOs\ProductFilterData;
use App\Modules\Shared\DTOs\PaginatedData;

interface ProductServiceInterface
{
    public function paginate(ProductFilterData $filter): PaginatedData;

    public function find(int $id): ProductDTO;

    public function create(ProductData $data, int $initialQuantity = 0, ?int $userId = null): ProductDTO;

    public function update(int $id, ProductData $data): ProductDTO;

    public function delete(int $id): void;
}
