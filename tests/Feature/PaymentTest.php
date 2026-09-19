<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_webhook_requires_auth(): void
    {
        $response = $this->postJson('/api/midtrans/notification', [
            'order_id' => 'TEST-001',
            'transaction_status' => 'capture',
            'status_code' => '200',
        ]);

        $response->assertStatus(403);
    }

    public function test_midtrans_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/midtrans/notification', [
            'order_id' => 'TEST-001',
            'transaction_status' => 'capture',
            'status_code' => '200',
        ], [
            'Authorization' => 'Basic '.base64_encode('invalid-key:'),
        ]);

        $response->assertStatus(403);
    }

    public function test_bri_va_webhook_handles_invalid_json(): void
    {
        // Kirim body bukan-JSON (invalid) dengan Content-Type JSON.
        // post() tidak menerima null sebagai data, jadi gunakan call()
        // dengan raw content agar webhook benar-benar menerima payload invalid.
        $response = $this->call(
            'POST',
            '/api/webhooks/bri/va',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid-json'
        );

        $response->assertOk()
            ->assertJson(['responseCode' => '2009700']);
    }

    public function test_bri_va_webhook_returns_200_for_unknown_transaction(): void
    {
        $payload = [
            'virtualAccountNo' => '880800000000000',
            'paidAmount' => ['value' => '100000.00', 'currency' => 'IDR'],
            'paymentFlagStatus' => 'PAID',
        ];

        $response = $this->postJson('/api/webhooks/bri/va', $payload);

        $response->assertOk()
            ->assertJson([
                'responseCode' => '2009700',
            ]);
    }

    public function test_bri_qris_webhook_returns_200_for_unknown_reference(): void
    {
        $payload = [
            'originalPartnerReferenceNo' => 'NONEXISTENT-REF-123',
            'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            'latestTransactionStatus' => '00',
        ];

        $response = $this->postJson('/api/webhooks/bri/qris', $payload);

        $response->assertOk()
            ->assertJson([
                'responseCode' => '2009700',
            ]);
    }

    public function test_transaction_mark_as_success_is_idempotent(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'topup',
            'amount' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        $transaction->markAsSuccess();
        $transaction->markAsSuccess(); // Should not double-increment

        $user->refresh();
        $this->assertEquals(100000, $user->balance);
    }

    public function test_transaction_mark_as_failed_sets_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'order',
            'status' => 'pending',
        ]);

        $transaction->markAsFailed('Payment gateway error');

        $transaction->refresh();
        $this->assertEquals('failed', $transaction->status->value);
        $this->assertEquals('Payment gateway error', $transaction->notes);
    }

    public function test_user_can_view_wallet_data(): void
    {
        $user = User::factory()->create(['balance' => 500000]);

        $response = $this->actingAs($user)->getJson('/api/wallet');

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_user_can_view_wallet_history(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->topup()->success()->create([
            'user_id' => $user->id,
            'amount' => 100000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/wallet/history');

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }
}
