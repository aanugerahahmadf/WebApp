<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
    }

    public function test_guest_can_start_conversation(): void
    {
        $guestId = str_repeat('a', 64);
        $response = $this->postJson('/api/messages/guest/start', [
            'guest_id' => $guestId,
            'name' => 'Tamu',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id']]);

        $this->assertDatabaseHas('users', ['email' => 'guest_'.md5($guestId).'@guest.local']);
        $this->assertDatabaseHas('fm_inboxes');
    }

    public function test_guest_can_fetch_messages(): void
    {
        $guestId = str_repeat('b', 64);
        $start = $this->postJson('/api/messages/guest/start', ['guest_id' => $guestId]);
        $inboxId = $start->json('data.id');

        $response = $this->getJson("/api/messages/guest/{$inboxId}?guest_id={$guestId}");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'other_user' => ['id', 'name']]);
    }

    public function test_guest_can_send_message(): void
    {
        $guestId = str_repeat('c', 64);
        $start = $this->postJson('/api/messages/guest/start', ['guest_id' => $guestId]);
        $inboxId = $start->json('data.id');

        $response = $this->postJson('/api/messages/guest/send', [
            'guest_id' => $guestId,
            'inbox_id' => $inboxId,
            'message' => 'Halo, ada yang bisa membantu?',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'message']]);
        $this->assertDatabaseHas('fm_messages', ['message' => 'Halo, ada yang bisa membantu?']);
    }

    public function test_guest_cannot_access_another_inbox(): void
    {
        $guestId = str_repeat('d', 64);
        $otherGuestId = str_repeat('e', 64);
        $start = $this->postJson('/api/messages/guest/start', ['guest_id' => $otherGuestId]);
        $inboxId = $start->json('data.id');

        $response = $this->getJson("/api/messages/guest/{$inboxId}?guest_id={$guestId}");

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_guest_cannot_access_nonexistent_inbox(): void
    {
        $guestId = str_repeat('f', 64);
        $response = $this->getJson('/api/messages/guest/999999?guest_id='.$guestId);
        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_guest_start_requires_guest_id(): void
    {
        $response = $this->postJson('/api/messages/guest/start', []);
        $response->assertStatus(422);
    }
}
