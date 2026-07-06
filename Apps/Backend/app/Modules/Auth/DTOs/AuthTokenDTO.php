<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Shared\DTOs\BaseData;

final class AuthTokenDTO extends BaseData
{
    public function __construct(
        public readonly string $token,
        public readonly UserDTO $user,
        public readonly string $tokenType = 'Bearer',
    ) {}

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'token_type' => $this->tokenType,
            'user' => $this->user->toArray(),
        ];
    }
}
