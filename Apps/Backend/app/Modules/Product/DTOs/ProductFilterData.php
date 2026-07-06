<?php

declare(strict_types=1);

namespace App\Modules\Product\DTOs;

/**
 * Input DTO describing the query filters for listing products. Built from the
 * request query string and sanitised (whitelisted sort columns, clamped paging).
 */
final class ProductFilterData
{
    private const SORTABLE = ['name', 'sku', 'price', 'quantity', 'reorder_level', 'created_at', 'updated_at'];

    public function __construct(
        public readonly ?string $search,
        public readonly ?int $categoryId,
        public readonly ?bool $lowStock,
        public readonly ?bool $isActive,
        public readonly string $sortBy,
        public readonly string $sortDir,
        public readonly int $perPage,
        public readonly int $page,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromArray(array $query): self
    {
        $search = isset($query['search']) && $query['search'] !== ''
            ? trim((string) $query['search'])
            : null;

        $categoryId = isset($query['category_id']) && $query['category_id'] !== ''
            ? (int) $query['category_id']
            : null;

        $sortBy = in_array($query['sort_by'] ?? null, self::SORTABLE, true)
            ? (string) $query['sort_by']
            : 'name';

        $sortDir = strtolower((string) ($query['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = isset($query['per_page']) ? max(1, min(100, (int) $query['per_page'])) : 15;
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;

        return new self(
            search: $search,
            categoryId: $categoryId,
            lowStock: self::toBool($query['low_stock'] ?? null),
            isActive: self::toBool($query['is_active'] ?? null),
            sortBy: $sortBy,
            sortDir: $sortDir,
            perPage: $perPage,
            page: $page,
        );
    }

    private static function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
