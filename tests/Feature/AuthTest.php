<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'super_admin']);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['token', 'user'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'full_name' => 'Test User',
            'username' => 'testuser2',
            'email' => 'test@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_register_with_duplicate_username(): void
    {
        User::factory()->create(['username' => 'testuser']);

        $response = $this->postJson('/api/register', [
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'another@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_register_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_register_without_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'username', 'email', 'password']);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'password123456',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['token', 'user'],
            ]);
    }

    public function test_user_can_login_with_username(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'testuser',
            'password' => 'password123456',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['token', 'user'],
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'nonexistent@example.com',
            'password' => 'password123456',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    public function test_forgot_password_returns_success_even_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_forgot_password_for_existing_user(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
