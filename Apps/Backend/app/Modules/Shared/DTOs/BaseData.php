<?php

declare(strict_types=1);

namespace App\Modules\Shared\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Base Data Transfer Object.
 *
 * Every DTO in the system is an immutable, framework-agnostic value object that
 * crosses layer boundaries (Controller <-> Service <-> Mapper). It knows how to
 * serialise itself to a plain array so controllers can return it directly as JSON.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class BaseData implements Arrayable, JsonSerializable
{
    /**
     * Convert the DTO to a primitive associative array (snake_case keys).
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
