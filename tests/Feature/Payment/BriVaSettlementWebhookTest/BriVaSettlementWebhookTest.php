<?php

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Services\BriService\BriService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| BRI Virtual Account / QRIS - Pelunasan otomatis lewat webhook
|--------------------------------------------------------------------------
|
| Yang dikunci di sini adalah janji utama alur pembayaran:
|
|   pembeli transfer ke nomor VA unik miliknya
|       -> BRI meneruskan dana ke rekening settlement 421201032041536
|       -> notifikasi BRI masuk
|       -> pesanan otomatis LUNAS, tanpa admin memverifikasi.
|
| Payload BRI SNAP membungkus semua data di `virtualAccountData`. Versi lama
| menaruhnya di root. Dua-duanya harus dilunasi, karena notifikasi yang sah
| tapi diabaikan terlihat persis seperti "uang tidak pernah masuk".
|
| BRI tidak dipanggil sungguhan: webhook hanya membaca database.
|
*/

beforeEach(function () {
    Cache::store('array')->flush();
    Mail::fake();

    config([
        'bri.enabled' => true,
        'bri.client_id' => 'test-client-id',
        'bri.client_secret' => 'test-client-secret',
        'bri.snap_enabled' => true,
        'bri.snap_key_type' => 'symmetric',
        'bri.webhook_require_signature' => true,
        'bri.va_partner_service_id' => '8808',
        'bri.account_number' => '421201032041536',
        'bri.account_holder' => 'Anugerah Ahmad Fachrurochim',
        'bri.amount_tolerance' => 500,
    ]);
});

/**
 * Order + transaksi pending milik satu user.
 */
function bvaPendingOrder(User $user, array $transactionOverrides = []): array
{
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-BVA-'.uniqid(),
        'total_price' => 150000,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'booking_date' => now()->addDays(7),
    ]);

    $transaction = Transaction::create(array_merge([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => 'order',
        'reference_number' => 'TRX-BVA-'.uniqid(),
        'amount' => 150000,
        'total_amount' => 150000,
        'status' => 'pending',
        'payment_gateway' => 'bri_va',
        'payment_method' => 'Virtual Account BRI',
        'virtual_account_no' => '880800000000001',
        'virtual_account_expiry' => now()->addDay(),
    ], $transactionOverrides));

    return [$order->fresh(), $transaction->fresh()];
}

/**
 * Kirim body mentah + signature yang dihitung dari body yang sama.
 *
 * `postJson()` menulis ulang body lewat json_encode, jadi signature tidak
 * bisa dihitung ulang dari situ. Webhook menerima byte mentah, dan BRI juga
 * menandatangani byte mentah -- jadi test memakai `call()`.
 */
function bvaSignedCall(string $uri, array $payload, ?string $signature = null, ?string $timestamp = null): \Illuminate\Testing\TestResponse
{
    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $timestamp ??= '2026-10-28T08:00:00.000Z';

    $signature ??= base64_encode(hash_hmac('sha512', $rawBody, 'test-client-secret', true));

    return test()->call(
        'POST',
        $uri,
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
        ],
        $rawBody
    );
}

/**
 * Payload notifikasi VA bentuk SNAP (data di dalam virtualAccountData).
 */
function bvaNestedPayload(Transaction $transaction, array $overrides = []): array
{
    return [
        'virtualAccountData' => array_merge([
            'partnerServiceId' => '8808',
            'virtualAccountNo' => $transaction->virtual_account_no,
            'virtualAccountName' => 'Rangga Wicaksono',
            'trxId' => $transaction->reference_number,
            'paidAmount' => ['value' => '150000.00', 'currency' => 'IDR'],
            'paymentFlagStatus' => '00',
        ], $overrides),
    ];
}

test('notifikasi VA bentuk SNAP otomatis melunasi pesanan', function () {
    $user = User::factory()->create(['full_name' => 'Rangga Wicaksono', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user);

    $response = bvaSignedCall('/api/webhooks/bri/va', bvaNestedPayload($transaction));

    $response->assertOk()->assertJson(['responseCode' => '2009700']);

    $transaction->refresh();
    $order->refresh();

    expect($transaction->status->value)->toBe('success')
        ->and($transaction->paid_at)->not->toBeNull()
        ->and($transaction->notes)->toBe(__('Pembayaran Virtual Account BRI otomatis terkonfirmasi'))
        // Payload asli disimpan untuk audit sengketa.
        ->and($transaction->metadata['bri_va_notification']['virtualAccountData']['virtualAccountNo'])
        ->toBe($transaction->virtual_account_no)
        // Pesanan ikut LUNAS, bukan hanya transaksinya.
        ->and($order->payment_status->value)->toBe('paid')
        ->and($order->status->value)->toBe('confirmed');
});

test('notifikasi VA bentuk lama (data di root) tetap dilunasi', function () {
    $user = User::factory()->create(['full_name' => 'Sinta Maharani', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user, [
        'virtual_account_no' => '880800000000002',
    ]);

    $response = bvaSignedCall('/api/webhooks/bri/va', [
        'virtualAccountNo' => '880800000000002',
        'paidAmount' => ['value' => '150000.00', 'currency' => 'IDR'],
        'paymentFlagStatus' => 'PAID',
    ]);

    $response->assertOk();

    expect($transaction->fresh()->status->value)->toBe('success')
        ->and($order->fresh()->payment_status->value)->toBe('paid');
});

test('signature SNAP berbasis path dan timestamp diterima', function () {
    $user = User::factory()->create(['full_name' => 'Dian Puspita', 'email' => '']);
    [, $transaction] = bvaPendingOrder($user);

    $payload = bvaNestedPayload($transaction);
    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $timestamp = '2026-10-28T08:10:00.000Z';
    $bodyHash = strtolower(hash('sha256', $rawBody));

    // Bentuk resmi SNAP: METHOD:EndpointUrl:AccessToken:bodyHash:Timestamp
    $signature = base64_encode(hash_hmac(
        'sha512',
        'POST:/v1.0/transfer-va/payment::'.$bodyHash.':'.$timestamp,
        'test-client-secret',
        true
    ));

    $response = bvaSignedCall('/api/webhooks/bri/va', $payload, $signature, $timestamp);

    $response->assertOk();

    expect($transaction->fresh()->status->value)->toBe('success');
});

test('signature salah tidak pernah melunasi pesanan', function () {
    $user = User::factory()->create(['full_name' => 'Bayu Setiawan', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user);

    $response = bvaSignedCall(
        '/api/webhooks/bri/va',
        bvaNestedPayload($transaction),
        'signature-palsu'
    );

    $response->assertOk()->assertJson(['info' => 'Invalid signature']);

    expect($transaction->fresh()->status->value)->toBe('pending')
        ->and($order->fresh()->payment_status->value)->toBe('unpaid');
});

test('nominal yang tidak cocok tidak melunasi pesanan', function () {
    $user = User::factory()->create(['full_name' => 'Nadia Ayu', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user);

    $response = bvaSignedCall('/api/webhooks/bri/va', bvaNestedPayload($transaction, [
        'paidAmount' => ['value' => '1000.00', 'currency' => 'IDR'],
    ]));

    $response->assertOk()->assertJson(['info' => 'Amount mismatch']);

    expect($transaction->fresh()->status->value)->toBe('pending')
        ->and($order->fresh()->payment_status->value)->toBe('unpaid');
});

test('nomor VA dengan padding spasi tetap ditemukan', function () {
    $user = User::factory()->create(['full_name' => 'Fajar Nugroho', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user, [
        'virtual_account_no' => '880800000000003',
    ]);

    $response = bvaSignedCall('/api/webhooks/bri/va', bvaNestedPayload($transaction, [
        // partnerServiceId di SNAP dipad dengan spasi.
        'virtualAccountNo' => '    8808 0000 0000 03',
    ]));

    $response->assertOk();

    expect($transaction->fresh()->status->value)->toBe('success')
        ->and($order->fresh()->payment_status->value)->toBe('paid');
});

test('notifikasi QRIS melunasi pesanan lewat referensi', function () {
    $user = User::factory()->create(['full_name' => 'Putri Amelia', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user, [
        'payment_gateway' => 'qris',
        'payment_method' => 'QRIS',
        'virtual_account_no' => null,
        'reference_number' => 'TRX-QRIS-'.uniqid(),
    ]);

    $response = bvaSignedCall('/api/webhooks/bri/qris', [
        'originalPartnerReferenceNo' => $transaction->reference_number,
        'amount' => ['value' => '150000.00', 'currency' => 'IDR'],
        'latestTransactionStatus' => '00',
    ]);

    $response->assertOk();

    expect($transaction->fresh()->status->value)->toBe('success')
        ->and($transaction->fresh()->notes)->toBe(__('Pembayaran QRIS otomatis terkonfirmasi'))
        ->and($order->fresh()->payment_status->value)->toBe('paid');
});

test('status QRIS yang gagal tidak menandai lunas', function () {
    $user = User::factory()->create(['full_name' => 'Hendra Wijaya', 'email' => '']);
    [, $transaction] = bvaPendingOrder($user, [
        'payment_gateway' => 'qris',
        'payment_method' => 'QRIS',
        'virtual_account_no' => null,
        'reference_number' => 'TRX-QRIS-'.uniqid(),
    ]);

    $response = bvaSignedCall('/api/webhooks/bri/qris', [
        'originalPartnerReferenceNo' => $transaction->reference_number,
        'amount' => ['value' => '150000.00', 'currency' => 'IDR'],
        'latestTransactionStatus' => '05',
    ]);

    $response->assertOk();

    expect($transaction->fresh()->status->value)->toBe('pending');
});

test('callback ganda tidak membalikkan status atau menggandakan proses', function () {
    $user = User::factory()->create(['full_name' => 'Indah Permata', 'email' => '']);
    [$order, $transaction] = bvaPendingOrder($user);

    bvaSignedCall('/api/webhooks/bri/va', bvaNestedPayload($transaction))->assertOk();
    bvaSignedCall('/api/webhooks/bri/va', bvaNestedPayload($transaction))->assertOk();

    expect($transaction->fresh()->status->value)->toBe('success')
        ->and($order->fresh()->payment_status->value)->toBe('paid');
});

test('transaksi VA yang dibuat menyimpan rekening settlement toko', function () {
    $user = User::factory()->create(['full_name' => 'Mega Lestari', 'email' => '']);

    Http::fake([
        '*/snap/v1.0/access-token/b2b' => Http::response(['accessToken' => 'fake-b2b-token'], 200),
        '*/snap/v1.0/transfer-va/create-va' => Http::response([
            'responseCode' => '2009000',
            'virtualAccountData' => [
                'virtualAccountNo' => '880800000000123',
                'expiredDate' => now()->addDay()->format('Y-m-d\TH:i:s.000P'),
            ],
        ], 200),
    ]);

    [, $transaction] = bvaPendingOrder($user, ['virtual_account_no' => null]);

    $result = app(BriService::class)->createVirtualAccount($transaction);

    expect($result['account_number'])->toBe('421201032041536')
        ->and($result['account_holder'])->toBe('Anugerah Ahmad Fachrurochim')
        ->and($result['virtual_account_no'])->toBe('880800000000123')
        ->and($transaction->fresh()->metadata['bri_va']['settlementAccount'])->toBe('421201032041536')
        ->and($transaction->fresh()->metadata['bri_va']['settlementAccountHolder'])->toBe('Anugerah Ahmad Fachrurochim');
});
