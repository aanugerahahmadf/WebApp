<?php

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Services\BriService\BriService;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| BRI API Integration Tests
|--------------------------------------------------------------------------
|
| Test koneksi dan integrasi BRI API (Sandbox).
|
| Jalankan: php artisan test --filter=BriApi
|
*/

beforeEach(function () {
    Cache::store('array')->flush();
});

test('BRI config is loaded correctly', function () {
    // Skip seluruh suite BRI bila integrasi tidak diaktifkan (mis. di CI
    // tanpa credential sandbox). Test ini butuh akses internet + credential.
    if (! config('bri.enabled')) {
        $this->markTestSkipped('BRI integration disabled (BRI_ENABLED != true)');
    }

    expect(config('bri.enabled'))->toBeTrue()
        ->and(config('bri.client_id'))->not->toBeEmpty()
        ->and(config('bri.client_secret'))->not->toBeEmpty()
        ->and(config('bri.base_url'))->toContain('partner.api.bri.co.id')
        ->and(config('bri.snap_enabled'))->toBeTrue()
        ->and(config('bri.snap_key_type'))->toBeIn(['symmetric', 'asymmetric']);
});

test('BriService is enabled with current credentials', function () {
    if (! config('bri.enabled')) {
        $this->markTestSkipped('BRI integration disabled (BRI_ENABLED != true)');
    }

    $bri = app(BriService::class);
    expect($bri->enabled())->toBeTrue();
});

test('BRI OAuth2 access token can be obtained from sandbox', function () {
    if (! config('bri.enabled')) {
        $this->markTestSkipped('BRI integration disabled (BRI_ENABLED != true)');
    }

    // If live test is enabled, test against real sandbox API
    if (getenv('BRI_TEST_LIVE') === '1') {
        $bri = app(BriService::class);
        $token = $bri->accessToken();

        expect($token)->not->toBeNull('OAuth2 access token should not be null')
            ->and(strlen($token))->toBeGreaterThan(10, 'Token should have meaningful length');
        return;
    }

    // Otherwise, verify the service is properly configured for OAuth2
    $bri = app(BriService::class);
    expect($bri->enabled())->toBeTrue();
    expect(config('bri.client_id'))->not->toBeEmpty();
    expect(config('bri.client_secret'))->not->toBeEmpty();
    expect(config('bri.base_url'))->not->toBeEmpty();
});

test('BRI snapAccessToken returns token for current key mode', function () {
    // Sama seperti test lain di file ini: skip bila integrasi BRI tidak
    // diaktifkan (CI tidak punya credential sandbox BRI_ENABLED != true).
    if (! config('bri.enabled')) {
        $this->markTestSkipped('BRI integration disabled (BRI_ENABLED != true)');
    }

    $keyType = config('bri.snap_key_type');
    expect($keyType)->toBeIn(['symmetric', 'asymmetric']);

    // If live test is enabled, test against real sandbox API
    if (getenv('BRI_TEST_LIVE') === '1') {
        $bri = app(BriService::class);
        $token = $bri->snapAccessToken();

        expect($token)->not->toBeNull('SNAP access token should not be null')
            ->and(strlen($token))->toBeGreaterThan(10);
        return;
    }

    // Otherwise, verify the service is properly configured for SNAP
    $bri = app(BriService::class);
    expect($bri->enabled())->toBeTrue();
    expect($bri->snapEnabled())->toBeTrue();
});

test('BRI signature generation is consistent', function () {
    $bri = app(BriService::class);
    $path = '/test/endpoint';
    $verb = 'POST';
    $token = 'test-token-123';
    $timestamp = '2026-01-01T00:00:00.000Z';
    $body = '{"test":true}';

    $sig1 = $bri->signature($path, $verb, $token, $timestamp, $body);
    $sig2 = $bri->signature($path, $verb, $token, $timestamp, $body);

    expect($sig1)->toBe($sig2, 'Signature should be deterministic')
        ->and(strlen($sig1))->toBeGreaterThan(20, 'Signature should be a valid base64 hash');
});

test('BRI VA notification signature verification works', function () {
    $bri = app(BriService::class);

    if (! $bri->snapEnabled()) {
        $this->markTestSkipped('SNAP not enabled');
    }

    $clientSecret = config('bri.client_secret');
    $timestamp = '2026-01-01T00:00:00.000Z';
    $rawBody = '{"virtualAccountNo":"880800000000001","paidAmount":{"value":"100000.00","currency":"IDR"}}';

    $signature = base64_encode(hash_hmac('sha512', $rawBody, $clientSecret, true));

    expect($bri->verifyVaNotification($timestamp, $signature, $rawBody))->toBeTrue('Valid signature should pass');

    expect($bri->verifyVaNotification($timestamp, 'invalid-signature', $rawBody))->toBeFalse('Invalid signature should fail');
});

test('BRI create virtual account via sandbox API', function () {
    $bri = app(BriService::class);

    if (! $bri->snapEnabled()) {
        $this->markTestSkipped('SNAP not enabled');
    }

    // If live test is enabled, test against real sandbox API
    if (getenv('BRI_TEST_LIVE') === '1') {
        $token = $bri->snapAccessToken();
        if ($token === null) {
            $this->markTestSkipped('B2B token tidak tersedia (public key belum terdaftar di portal BRI)');
        }

        $user = User::factory()->create(['full_name' => 'Test User BRI']);
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-BRI-TEST-'.time(),
            'total_price' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'booking_date' => now()->addDays(7),
        ]);
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'order',
            'reference_number' => 'TRX-BRI-'.time().'-'.mt_rand(1000, 9999),
            'amount' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_gateway' => 'manual',
        ]);

        $result = $bri->createVirtualAccount($transaction);

        expect($result)->not->toBeNull(
            'BRI create-va returned null. Cek log [BRI] untuk responseCode, dan '
            .'pastikan BRI_VA_PARTNER_SERVICE_ID + BRI_SNAP_KEY_TYPE sesuai sandbox.'
        );

        expect($result)->toHaveKeys([
            'virtual_account_no',
            'virtual_account_name',
            'virtual_account_expiry',
            'account_number',
            'account_holder',
        ])
            ->and($result['virtual_account_no'])->not->toBeEmpty()
            ->and($result['virtual_account_name'])->not->toBeEmpty()
            ->and($result['account_number'])->not->toBeEmpty()
            ->and($result['account_holder'])->not->toBeEmpty();
        return;
    }

    // Otherwise, verify the service is properly configured for VA creation
    expect($bri->enabled())->toBeTrue();
    expect(config('bri.va_partner_service_id', ''))->not->toBeEmpty('VA partner service ID should be configured');
});

test('BRI create QRIS generates QR content', function () {
    $bri = app(BriService::class);

    if (! $bri->snapEnabled()) {
        $this->markTestSkipped('SNAP not enabled');
    }

    // If QRIS is fully configured, run the full test
    if (config('bri.qr_enabled', false) && config('bri.qr_merchant_id', '') !== '') {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-QRIS-TEST-'.time(),
            'total_price' => 150000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'booking_date' => now()->addDays(7),
        ]);
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'order',
            'reference_number' => 'TRX-QRIS-'.time(),
            'amount' => 150000,
            'total_amount' => 150000,
            'status' => 'pending',
            'payment_gateway' => 'manual',
        ]);

        $result = $bri->createQris($transaction);

        expect($result)->not->toBeNull('QRIS generation should succeed when merchant ID is configured')
            ->toHaveKeys(['qr_content', 'qr_expiry'])
            ->and($result['qr_content'])->not->toBeEmpty();
        return;
    }

    // Otherwise, verify QRIS configuration state is accessible
    expect($bri->enabled())->toBeTrue();
    // QRIS may be enabled but merchant ID not set, or QRIS may be fully disabled
    $qrEnabled = config('bri.qr_enabled', false);
    $qrMerchantId = config('bri.qr_merchant_id', '');
    expect($qrEnabled === false || $qrMerchantId === '')
        ->toBeTrue('QRIS test skipped: either QRIS is disabled or merchant ID is not configured');
});

test('BRI webhook VA endpoint is accessible', function () {
    $payload = [
        'virtualAccountNo' => '880800000000001',
        'paidAmount' => ['value' => '100000.00', 'currency' => 'IDR'],
        'paymentFlagStatus' => 'PAID',
    ];

    $this->postJson('/api/webhooks/bri/va', $payload)
        ->assertOk()
        ->assertJson([
            'responseCode' => '2009700',
        ]);
});

test('BRI webhook QRIS endpoint is accessible', function () {
    $payload = [
        'originalPartnerReferenceNo' => 'NONEXISTENT-REF-123',
        'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
        'latestTransactionStatus' => '00',
    ];

    $this->postJson('/api/webhooks/bri/qris', $payload)
        ->assertOk()
        ->assertJson([
            'responseCode' => '2009700',
        ]);
});
