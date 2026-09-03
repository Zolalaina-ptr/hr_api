<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'jean@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonPath('token', fn ($token) => ! empty($token));

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Déconnexion réussie.');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_profile_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_profile_route_returns_current_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $expiredToken = $user->createToken('expired-token', ['*'], now()->subMinute())->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$expiredToken)
            ->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_admin_role_can_access_admin_route(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Bienvenue admin.');
    }

    public function test_user_without_permission_cannot_access_users_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_access_users_route(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate([
            'name' => 'view users',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
