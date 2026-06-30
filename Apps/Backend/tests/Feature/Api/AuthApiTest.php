<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_user_and_returns_a_token(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']]]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_validation_fails_for_bad_input(): void
    {
        $this->postJson('/api/auth/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'demo@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'demo@example.com');
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'demo@example.com', 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_full_token_lifecycle_register_use_logout_revoke(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Logout must revoke the token at the data layer.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
