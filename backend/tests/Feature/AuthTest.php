<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $this->seedUser();

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->seedUser();

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->getJson('/api/organization')->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_and_logout(): void
    {
        $user = $this->seedUser();

        $this->actingAs($user);
        $this->getJson('/api/organization')->assertOk()->assertJsonPath('organization', null);
        $this->postJson('/api/logout')->assertOk();
    }
}
