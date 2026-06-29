<?php

declare(strict_types=1);

namespace App\Modules\Category\Services\Contracts;

use App\Modules\Category\DTOs\CategoryData;
use App\Modules\Category\DTOs\CategoryDTO;

interface CategoryServiceInterface
{
    /**
     * @return array<int, CategoryDTO>
     */
    public function list(): array;

    public function find(int $id): CategoryDTO;

    public function create(CategoryData $data): CategoryDTO;

    public function update(int $id, CategoryData $data): CategoryDTO;

    public function delete(int $id): void;
}
