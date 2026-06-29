<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services\Contracts;

use App\Modules\Auth\DTOs\AuthTokenDTO;
use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\DTOs\UserDTO;
use App\Modules\Auth\Models\User;

/**
 * Service contract for the authentication module.
 *
 * The interface IS the "Service" layer; the concrete class behind it holds the
 * business logic. Controllers depend only on this abstraction.
 */
interface AuthServiceInterface
{
    public function register(RegisterData $data): AuthTokenDTO;

    public function login(LoginData $data): AuthTokenDTO;

    public function logout(User $user): void;

    public function me(User $user): UserDTO;
}
