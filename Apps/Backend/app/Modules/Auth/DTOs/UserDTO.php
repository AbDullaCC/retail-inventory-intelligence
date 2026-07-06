<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Shared\DTOs\BaseData;

final class UserDTO extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->createdAt,
        ];
    }
}
