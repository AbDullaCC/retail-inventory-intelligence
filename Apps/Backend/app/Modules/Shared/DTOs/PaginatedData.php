<?php

declare(strict_types=1);

namespace App\Modules\Shared\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A generic, layer-safe pagination envelope.
 *
 * Services return this instead of leaking Eloquent's LengthAwarePaginator to the
 * HTTP layer. Items are already-mapped DTOs (or arrays), keeping the contract clean.
 */
final class PaginatedData extends BaseData
{
    /**
     * @param  array<int, mixed>  $items  Already-mapped DTOs/arrays for the current page.
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage,
    ) {
    }

    /**
     * Build the envelope from an Eloquent paginator, mapping each model via $mapItem.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  callable(mixed): mixed  $mapItem
     */
    public static function fromPaginator(LengthAwarePaginator $paginator, callable $mapItem): self
    {
        $items = array_map($mapItem, $paginator->items());

        return new self(
            items: array_values($items),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * @return array{data: array<int, mixed>, meta: array<string, int|null>}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                static fn ($item) => $item instanceof Arrayable ? $item->toArray() : $item,
                $this->items,
            ),
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'from' => $this->total === 0 ? null : ($this->currentPage - 1) * $this->perPage + 1,
                'to' => $this->total === 0
                    ? null
                    : min($this->currentPage * $this->perPage, $this->total),
            ],
        ];
    }
}
