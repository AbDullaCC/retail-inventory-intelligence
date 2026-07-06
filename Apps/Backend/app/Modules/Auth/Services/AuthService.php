<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\AuthTokenDTO;
use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\DTOs\UserDTO;
use App\Modules\Auth\Mappers\UserMapper;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Business logic for authentication: account creation, credential verification
 * and Sanctum personal-access-token lifecycle.
 */
final class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserMapper $mapper,
    ) {}

    public function register(RegisterData $data): AuthTokenDTO
    {
        // The 'hashed' cast on the model hashes the password on assignment.
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);

        return $this->issueToken($user);
    }

    public function login(LoginData $data): AuthTokenDTO
    {
        $user = User::query()->where('email', $data->email)->first();

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->issueToken($user);
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function me(User $user): UserDTO
    {
        return $this->mapper->toDTO($user);
    }

    private function issueToken(User $user): AuthTokenDTO
    {
        $token = $user->createToken('api-token')->plainTextToken;

        return new AuthTokenDTO(
            token: $token,
            user: $this->mapper->toDTO($user),
        );
    }
}
