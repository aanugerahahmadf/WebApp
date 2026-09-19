<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'full_name', 'email'],
            ]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'full_name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $user->refresh();
        $this->assertEquals('Updated Name', $user->full_name);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_delete_account_requires_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123456')]);

        $response = $this->actingAs($user)->deleteJson('/api/user/account');

        $response->assertStatus(422);
    }

    public function test_delete_account_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123456')]);

        $response = $this->actingAs($user)->deleteJson('/api/user/account', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_delete_account_works_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123456')]);

        $response = $this->actingAs($user)->deleteJson('/api/user/account', [
            'password' => 'password123456',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123456')]);
        $user->createToken('test-token-1');
        $user->createToken('test-token-2');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->actingAs($user)->deleteJson('/api/user/account', [
            'password' => 'password123456',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_cannot_delete_account(): void
    {
        $response = $this->deleteJson('/api/user/account', [
            'password' => 'password123456',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_get_auth_user_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'full_name', 'email'],
            ]);
    }
}
