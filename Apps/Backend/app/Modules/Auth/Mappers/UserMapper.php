<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mappers;

use App\Modules\Auth\DTOs\UserDTO;
use App\Modules\Auth\Models\User;

/**
 * Translates the User Eloquent model into its DTO representation.
 */
final class UserMapper
{
    public function toDTO(User $user): UserDTO
    {
        return new UserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->created_at?->toIso8601String(),
        );
    }
}
