<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer for authentication. Validates input, delegates to the service,
 * and returns the resulting DTO through the shared response envelope.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $token = $this->auth->register(RegisterData::fromArray($request->validated()));

        return ApiResponse::item($token, 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->auth->login(LoginData::fromArray($request->validated()));

        return ApiResponse::item($token, 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::message('Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::item($this->auth->me($request->user()));
    }
}
