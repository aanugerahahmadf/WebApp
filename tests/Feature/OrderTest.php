<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user']);
    }

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_user_can_create_order(): void
    {
        $user = $this->actingAsUser();
        $package = Package::factory()->create(['stock' => 10, 'price' => 1000000]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'package_id' => $package->id,
                'quantity' => 1,
                'event_date' => now()->addDays(30)->format('Y-m-d'),
                'customer_name' => $user->full_name,
                'whatsapp' => '081234567890',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'order_number',
                    'total_price',
                    'transaction' => ['id', 'reference_number', 'total_amount', 'status'],
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $package->id,
        ]);
    }

    public function test_stock_is_decremented_atomically(): void
    {
        $user = $this->actingAsUser();
        $package = Package::factory()->create(['stock' => 5, 'price' => 1000000]);

        $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'quantity' => 2,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);

        $package->refresh();
        $this->assertEquals(3, $package->stock);
    }

    public function test_order_fails_with_insufficient_stock(): void
    {
        $user = $this->actingAsUser();
        $package = Package::factory()->create(['stock' => 2, 'price' => 1000000]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'quantity' => 5,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);

        $response->assertStatus(400)
            ->assertJson(['status' => 'error']);

        $package->refresh();
        $this->assertEquals(2, $package->stock);
    }

    public function test_order_fails_with_nonexistent_package(): void
    {
        $user = $this->actingAsUser();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => 99999,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);

        $response->assertStatus(404);
    }

    public function test_order_fails_without_required_fields(): void
    {
        $user = $this->actingAsUser();

        $response = $this->actingAs($user)->postJson('/api/orders', []);

        $response->assertStatus(422);
    }

    public function test_order_fails_with_past_event_date(): void
    {
        $user = $this->actingAsUser();
        $package = Package::factory()->create(['stock' => 10, 'price' => 1000000]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'quantity' => 1,
            'event_date' => now()->subDays(5)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_view_their_orders(): void
    {
        $user = $this->actingAsUser();
        Order::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination',
            ]);
    }

    public function test_user_cannot_view_other_users_orders(): void
    {
        $user1 = $this->actingAsUser();
        $user2 = User::factory()->create();
        $user2->assignRole('user');
        Order::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/orders');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_user_can_view_order_detail(): void
    {
        $user = $this->actingAsUser();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'order_number', 'total_price'],
            ]);
    }

    public function test_user_cannot_view_other_users_order_detail(): void
    {
        $user1 = $this->actingAsUser();
        $user2 = User::factory()->create();
        $user2->assignRole('user');
        $order = Order::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson("/api/orders/{$order->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_cancel_pending_order(): void
    {
        $user = $this->actingAsUser();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status->value);
    }

    public function test_user_cannot_cancel_completed_order(): void
    {
        $user = $this->actingAsUser();
        $order = Order::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(400);
    }

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $package = Package::factory()->create(['stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'package_id' => $package->id,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => 'Test',
            'whatsapp' => '081234567890',
        ]);

        $response->assertStatus(401);
    }

    public function test_transaction_is_created_with_order(): void
    {
        $user = $this->actingAsUser();
        $package = Package::factory()->create(['stock' => 10, 'price' => 2000000]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);

        $response->assertCreated();

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('transactions', [
            'order_id' => $orderId,
            'type' => 'order',
            'status' => 'pending',
        ]);
    }
}
