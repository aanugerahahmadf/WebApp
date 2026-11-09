<?php

namespace Tests\Feature\Shared\OrderTest;

use App\Models\Catalog\Catalog;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Transaction\Transaction;
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
        $catalog = Catalog::factory()->create();
        $package = Package::factory()->create(['catalog_id' => $catalog->id, 'stock' => 10, 'price' => 1000000]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'package_id' => $package->id,
                'catalog_id' => $catalog->id,
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
        $catalog = Catalog::factory()->create();
        $package = Package::factory()->create(['catalog_id' => $catalog->id, 'stock' => 5, 'price' => 1000000]);

        $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'catalog_id' => $catalog->id,
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
        $catalog = Catalog::factory()->create();
        $package = Package::factory()->create(['catalog_id' => $catalog->id, 'stock' => 2, 'price' => 1000000]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'catalog_id' => $catalog->id,
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
        $catalog = Catalog::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => 99999,
            'catalog_id' => $catalog->id,
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
        $catalog = Catalog::factory()->create();
        $package = Package::factory()->create(['catalog_id' => $catalog->id, 'stock' => 10, 'price' => 2000000]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $package->id,
            'catalog_id' => $catalog->id,
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

    /**
     * Verifikasi bahwa harga tiers (finalPrice) dari catalog API sinkron
     * dengan harga di order (total_price) dan transaction (total_amount).
     *
     * Rantai: catalog tier finalPrice → order total_price → transaction total_amount
     * → payment amount (VA/QRIS). Semua harus sama.
     */
    public function test_tier_price_sync_from_catalog_to_order_to_transaction(): void
    {
        $user = $this->actingAsUser();
        $catalog = \App\Models\Catalog\Catalog::factory()->create();

        // Tier tanpa diskon: finalPrice = price
        $tierNoDiscount = Package::factory()->create([
            'catalog_id' => $catalog->id,
            'stock' => 10,
            'price' => 2500000,
            'discount_price' => null,
        ]);

        // Tier dengan diskon: finalPrice = discount_price
        $tierWithDiscount = Package::factory()->create([
            'catalog_id' => $catalog->id,
            'stock' => 10,
            'price' => 3000000,
            'discount_price' => 2200000,
        ]);

        // 1. Verifikasi finalPrice dari model Package
        $this->assertEquals(2500000, $tierNoDiscount->final_price);
        $this->assertEquals(2200000, $tierWithDiscount->final_price);

        // 2. Buat order dengan tier tanpa diskon
        $response = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $tierNoDiscount->id,
            'catalog_id' => $catalog->id,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);
        $response->assertCreated();

        $orderData = $response->json('data');
        $transaction = $orderData['transaction'];

        // 3. Verifikasi rantai harga: tier finalPrice → order total_price → transaction total_amount
        $this->assertEquals(
            (float) $tierNoDiscount->final_price,
            (float) $orderData['total_price'],
            'Order total_price harus sama dengan tier final_price'
        );
        $this->assertEquals(
            (float) $tierNoDiscount->final_price,
            (float) $transaction['total_amount'],
            'Transaction total_amount harus sama dengan tier final_price'
        );

        // 4. Buat order dengan tier yang punya diskon
        $response2 = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $tierWithDiscount->id,
            'catalog_id' => $catalog->id,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);
        $response2->assertCreated();

        $orderData2 = $response2->json('data');
        $transaction2 = $orderData2['transaction'];

        // 5. Verifikasi diskon diterapkan dengan benar
        $this->assertEquals(
            (float) $tierWithDiscount->final_price,
            (float) $orderData2['total_price'],
            'Order total_price harus sama dengan tier final_price (dengan diskon)'
        );
        $this->assertEquals(
            (float) $tierWithDiscount->final_price,
            (float) $transaction2['total_amount'],
            'Transaction total_amount harus sama dengan tier final_price (dengan diskon)'
        );

        // 6. Verifikasi payment amount dari response
        $paymentAmount = $orderData['payment']['amount'] ?? null;
        $this->assertNotNull($paymentAmount, 'Payment amount harus ada di response');
        $this->assertEquals(
            (float) $tierNoDiscount->final_price,
            (float) $paymentAmount,
            'Payment amount harus sama dengan tier final_price'
        );
    }

    /**
     * Verifikasi bahwa harga tiers dari model Package (finalPrice) sinkron
     * dengan harga yang digunakan saat order, termasuk tier yang berasal
     * dari catalog yang sama.
     */
    public function test_catalog_tiers_api_returns_final_price_sync_with_order(): void
    {
        $user = $this->actingAsUser();

        // Buat catalog dengan tier
        $catalog = \App\Models\Catalog\Catalog::factory()->create();
        $tier = Package::factory()->create([
            'catalog_id' => $catalog->id,
            'stock' => 10,
            'price' => 1800000,
            'discount_price' => 1500000,
            'sort_order' => 1,
        ]);

        // 1. Verifikasi finalPrice dari model Package (sumber kebenaran)
        $modelFinalPrice = (float) $tier->final_price;
        $this->assertEquals(
            1500000,
            $modelFinalPrice,
            'Model Package final_price harus sama dengan discount_price'
        );

        // 2. Buat order dengan tier yang sama
        $orderResponse = $this->actingAs($user)->postJson('/api/orders', [
            'package_id' => $tier->id,
            'catalog_id' => $catalog->id,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ]);
        $orderResponse->assertCreated();

        // 3. Verifikasi harga order sama dengan finalPrice model
        $this->assertEquals(
            $modelFinalPrice,
            (float) $orderResponse->json('data.total_price'),
            'Harga order harus sama dengan finalPrice dari model Package'
        );

        // 4. Verifikasi transaction total_amount juga sama
        $this->assertEquals(
            $modelFinalPrice,
            (float) $orderResponse->json('data.transaction.total_amount'),
            'Transaction total_amount harus sama dengan finalPrice dari model Package'
        );

        // 5. Verifikasi payment amount dari PaymentWindow
        $this->assertEquals(
            $modelFinalPrice,
            (float) ($orderResponse->json('data.payment.amount') ?? 0),
            'Payment amount harus sama dengan finalPrice dari model Package'
        );
    }

    /**
     * Order dibuat lewat API supaya transaksi pending-nya ikut terbentuk,
     * sama seperti alur checkout mobile.
     */
    private function createOrderViaApi(User $user, Package $package, array $overrides = []): array
    {
        $response = $this->actingAs($user)->postJson('/api/orders', array_merge([
            'package_id' => $package->id,
            'catalog_id' => $package->catalog_id,
            'quantity' => 1,
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_name' => $user->full_name,
            'whatsapp' => '081234567890',
        ], $overrides));

        $response->assertCreated();

        return $response->json('data');
    }

    private function pendingPackage(User $user): Package
    {
        $catalog = Catalog::factory()->create();

        return Package::factory()->create([
            'catalog_id' => $catalog->id,
            'stock' => 10,
            'price' => 1000000,
            'discount_price' => null,
        ]);
    }

    public function test_history_endpoint_returns_payment_fields(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        Transaction::where('order_id', $order['id'])->update([
            'checkout_payment_type' => 'virtual_account',
            'virtual_account_no' => '8803123456789012',
            'virtual_account_expiry' => now()->addHours(12),
        ]);

        $row = $this->actingAs($user)->getJson('/api/orders')->json('data.0');

        $this->assertSame('virtual_account', $row['payment_type']);
        $this->assertSame('8803123456789012', $row['va_number']);
        $this->assertNull($row['qris_payload']);
        $this->assertEquals((float) $order['total_price'], (float) $row['payment']['amount']);
        $this->assertNotNull($row['payment']['deadline']);
        $this->assertFalse($row['payment']['is_expired']);
        $this->assertTrue($row['can_edit']);
        $this->assertTrue($row['can_delete']);
        $this->assertTrue($row['can_pay']);
    }

    public function test_history_filters_by_payment_type(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $vaOrder = $this->createOrderViaApi($user, $package);
        $qrisOrder = $this->createOrderViaApi($user, $package);

        Transaction::where('order_id', $vaOrder['id'])->update(['checkout_payment_type' => 'virtual_account']);
        Transaction::where('order_id', $qrisOrder['id'])->update(['checkout_payment_type' => 'qris']);

        $qrisOnly = $this->actingAs($user)->getJson('/api/orders?payment_type=qris');

        $qrisOnly->assertOk();
        $this->assertCount(1, $qrisOnly->json('data'));
        $this->assertSame($qrisOrder['id'], $qrisOnly->json('data.0.id'));
    }

    public function test_history_filters_by_one_sided_date_range(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $soon = $this->createOrderViaApi($user, $package, ['event_date' => now()->addDays(5)->format('Y-m-d')]);
        $later = $this->createOrderViaApi($user, $package, ['event_date' => now()->addDays(60)->format('Y-m-d')]);

        $fromNextWeek = $this->actingAs($user)->getJson(
            '/api/orders?from_date='.now()->addDays(30)->format('Y-m-d')
        );

        $fromNextWeek->assertOk();
        $this->assertSame($later['id'], $fromNextWeek->json('data.0.id'));
        $this->assertCount(1, $fromNextWeek->json('data'));

        $toNextWeek = $this->actingAs($user)->getJson(
            '/api/orders?to_date='.now()->addDays(30)->format('Y-m-d')
        );
        $toNextWeek->assertOk();
        $this->assertSame($soon['id'], $toNextWeek->json('data.0.id'));
    }

    public function test_user_can_update_pending_order_and_totals_recalculate(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order['id']}", [
            'quantity' => 3,
            'event_date' => now()->addDays(45)->format('Y-m-d'),
            'event_time' => '10:00',
            'notes' => 'Bunga putih',
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertSame(3000000.0, (float) $response->json('data.total_price'));
        $this->assertSame(3, (int) $response->json('data.quantity'));
        $this->assertSame('10:00:00', $response->json('data.booking_time'));
        $this->assertSame(3000000.0, (float) $response->json('data.payment.amount'));

        // Stok mengikuti selisih quantity, dan nominal transaksi ikut disinkron.
        $package->refresh();
        $this->assertSame(7, (int) $package->stock);
        $this->assertSame(
            3000000.0,
            (float) Transaction::where('order_id', $order['id'])->value('total_amount')
        );
    }

    public function test_update_rejects_quantity_above_available_stock(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package, ['quantity' => 2]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order['id']}", ['quantity' => 99]);

        $response->assertStatus(400)->assertJson(['status' => 'error']);

        $package->refresh();
        $this->assertSame(8, (int) $package->stock);
        $this->assertSame(2, (int) Order::find($order['id'])->quantity);
    }

    public function test_update_is_rejected_when_order_is_paid(): void
    {
        $user = $this->actingAsUser();
        $order = Order::factory()->confirmed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}", ['quantity' => 4]);

        $response->assertStatus(409);
        $this->assertNotSame(4, (int) $order->fresh()->quantity);
    }

    public function test_update_can_switch_checkout_payment_method(): void
    {
        config([
            'bri.snap_enabled' => true,
            'bri.snap_key_type' => 'symmetric',
            'bri.client_id' => 'test-client-id',
            'bri.client_secret' => 'test-client-secret',
            'bri.qr_enabled' => true,
        ]);

        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $this->actingAs($user)
            ->putJson("/api/orders/{$order['id']}", ['payment_type' => 'qris'])
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'order_id' => $order['id'],
            'checkout_payment_type' => 'qris',
        ]);

        $this->assertSame('qris', $this->actingAs($user)->getJson("/api/orders/{$order['id']}")->json('data.payment_type'));
    }

    public function test_update_rejects_payment_method_when_gateway_is_disabled(): void
    {
        config(['bri.snap_enabled' => false]);

        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $this->actingAs($user)
            ->putJson("/api/orders/{$order['id']}", ['payment_type' => 'virtual_account'])
            ->assertStatus(400);

        $this->assertNull(Transaction::where('order_id', $order['id'])->value('checkout_payment_type'));
    }

    public function test_user_can_delete_order_from_history(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $this->actingAs($user)
            ->deleteJson("/api/orders/{$order['id']}")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSoftDeleted('orders', ['id' => $order['id']]);
        $this->assertEmpty($this->actingAs($user)->getJson('/api/orders')->json('data'));

        // Data finansial tidak ikut hilang: transaksi dan order masih di DB.
        $this->assertDatabaseHas('transactions', ['order_id' => $order['id']]);
        $this->assertNotNull(Order::withTrashed()->find($order['id']));
    }

    public function test_paid_order_cannot_be_deleted(): void
    {
        $user = $this->actingAsUser();
        $order = Order::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/orders/{$order->id}")->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_other_user_cannot_update_or_delete_order(): void
    {
        $owner = $this->actingAsUser();
        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        $package = $this->pendingPackage($owner);
        $order = $this->createOrderViaApi($owner, $package);

        $this->actingAs($intruder)->putJson("/api/orders/{$order['id']}", ['quantity' => 5])->assertStatus(404);
        $this->actingAs($intruder)->deleteJson("/api/orders/{$order['id']}")->assertStatus(404);

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'deleted_at' => null]);
    }

    public function test_payment_that_arrives_after_delete_still_confirms_order(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $this->actingAs($user)->deleteJson("/api/orders/{$order['id']}")->assertOk();

        $transaction = Transaction::where('order_id', $order['id'])->first();
        $transaction->markAsSuccess();

        $fresh = Order::find($order['id']);
        $this->assertNotNull($fresh, 'Order lunas harus kembali tampil di history');
        $this->assertSame('confirmed', $fresh->status->value);
        $this->assertSame('paid', $fresh->payment_status->value);
    }

    public function test_bookings_alias_supports_update_and_delete(): void
    {
        $user = $this->actingAsUser();
        $package = $this->pendingPackage($user);
        $order = $this->createOrderViaApi($user, $package);

        $this->actingAs($user)
            ->putJson("/api/bookings/{$order['id']}", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.quantity', 2);

        $this->actingAs($user)->deleteJson("/api/bookings/{$order['id']}")->assertOk();
        $this->assertSoftDeleted('orders', ['id' => $order['id']]);
    }
}
