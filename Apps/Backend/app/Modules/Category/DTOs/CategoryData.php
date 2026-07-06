<?php

declare(strict_types=1);

namespace App\Modules\Category\DTOs;

use App\Modules\Shared\DTOs\BaseData;

/**
 * Input DTO carrying validated data for creating/updating a category.
 */
final class CategoryData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
