<?php

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Services\BriService\BriService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| BRI Virtual Account - Nama Pemilik & Bentuk Request
|--------------------------------------------------------------------------
|
| Dua hal yang dikunci test ini:
|
| 1. Virtual Account BRI menampilkan `virtualAccountName` apa adanya di
|    m-banking/ATM pemesan. Jadi field itu berisi nama pemesan sendiri,
|    bukan nama merchant, walau BRI terlihat "aneh" karena nama merchant-nya
|    hilang. Dana tetap masuk ke rekening admin lewat routing BRI.
|
| 2. Body request ke BRI harus berupa objek JSON. Argumen kedua `Http::post()`
|    dipetakan ke opsi Guzzle `json` yang meng-encode lagi, sehingga string
|    JSON ikut ter-encode dua kali dan BRI menolak. Signature HMAC pun harus
|    dihitung atas byte yang benar-benar dikirim.
|
| Test memakai Http::fake(), jadi tidak memanggil BRI sungguhan.
|
*/

beforeEach(function () {
    Cache::store('array')->flush();

    config([
        'bri.enabled' => true,
        'bri.client_id' => 'test-client-id',
        'bri.client_secret' => 'test-client-secret',
        'bri.snap_enabled' => true,
        'bri.snap_key_type' => 'symmetric',
        'bri.va_partner_service_id' => '8808',
        'bri.account_number' => '421201032041536',
        'bri.account_holder' => 'Anugerah Ahmad Fachrurochim',
    ]);
});

/**
 * Order + transaksi untuk sebuah user, supaya relasi `user` terisi.
 */
function vaTransactionFor(User $user, int $amount = 100000): Transaction
{
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-VA-'.uniqid(),
        'total_price' => $amount,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'booking_date' => now()->addDays(7),
    ]);

    return Transaction::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => 'order',
        'reference_number' => 'TRX-VA-'.uniqid(),
        'amount' => $amount,
        'total_amount' => $amount,
        'status' => 'pending',
        'payment_gateway' => 'manual',
    ]);
}

/**
 * Fake respons BRI: token B2B lalu create-va yang sukses.
 */
function fakeSnapTokenAndCreateVa(array $vaData = []): void
{
    Http::fake([
        '*/snap/v1.0/access-token/b2b' => Http::response([
            'accessToken' => 'fake-b2b-token',
        ], 200),
        '*/snap/v1.0/transfer-va/create-va' => Http::response([
            'responseCode' => '2009000',
            'responseMessage' => 'Success',
            'virtualAccountData' => array_merge([
                'virtualAccountNo' => '880800000000001',
                'expiredDate' => now()->addDay()->format('Y-m-d\TH:i:s.000P'),
            ], $vaData),
        ], 200),
    ]);
}

/**
 * Body request create-va yang tercatat, atau null kalau tidak pernah dikirim.
 */
function capturedCreateVaBody(): ?array
{
    foreach (Http::recorded() as $pair) {
        if (! str_contains($pair[0]->url(), '/transfer-va/create-va')) {
            continue;
        }

        $decoded = json_decode($pair[0]->body(), true);

        // Kalau body-nya string JSON, decode pertama mengembalikan string;
        // decode kedua yang menghasilkan arraynya.
        return is_string($decoded) ? json_decode($decoded, true) : $decoded;
    }

    return null;
}

test('nama Virtual Account memakai nama lengkap pemesan, bukan nama merchant', function () {
    $user = User::factory()->create(['full_name' => 'Siti Rahmawati Putri']);

    fakeSnapTokenAndCreateVa();

    app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    $body = capturedCreateVaBody();

    expect($body)->not->toBeNull()
        // Yang dikirim ke BRI adalah nama pemesan.
        ->and($body['virtualAccountName'])->toBe('Siti Rahmawati Putri')
        // Nama merchant tidak boleh ikut di field nama Virtual Account.
        ->and($body['virtualAccountName'])->not->toBe(config('bri.account_holder'));
});

test('nama Virtual Account yang dikembalikan API adalah nama pemesan', function () {
    $user = User::factory()->create(['full_name' => 'Budi Santoso']);

    fakeSnapTokenAndCreateVa();

    $result = app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    expect($result['virtual_account_name'])->toBe('Budi Santoso');
});

test('nama Virtual Account yang dikirim BRI diprioritaskan atas nama lokal', function () {
    $user = User::factory()->create(['full_name' => 'Dewi Lestari']);

    // BRI kadang menyimpan nama dalam huruf kapital; ikutkan yang dikembalikan
    // BRI supaya antarmuka tidak menampilkan nama basi.
    fakeSnapTokenAndCreateVa(['virtualAccountName' => 'DEWI LESTARI']);

    $result = app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    expect($result['virtual_account_name'])->toBe('DEWI LESTARI');
});

test('nama Virtual Account dipotong 40 karakter sesuai batas BRI', function () {
    $longName = str_repeat('NamaPanjang', 10);
    expect(strlen($longName))->toBeGreaterThan(40);

    $user = User::factory()->create(['full_name' => $longName]);

    fakeSnapTokenAndCreateVa();

    app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    $name = capturedCreateVaBody()['virtualAccountName'];

    expect(strlen($name))->toBe(40)
        ->and($name)->toBe(mb_substr($longName, 0, 40));
});

test('nama Virtual Account di bawah 40 karakter tidak ikut dipotong', function () {
    expect(mb_strlen('Rina Wijaya Kusuma Wardani'))->toBeLessThan(40);

    $user = User::factory()->create(['full_name' => 'Rina Wijaya Kusuma Wardani']);

    fakeSnapTokenAndCreateVa();

    app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    expect(capturedCreateVaBody()['virtualAccountName'])->toBe('Rina Wijaya Kusuma Wardani');
});

test('tanpa nama lengkap, Virtual Account memakai username', function () {
    $user = User::factory()->create();
    $transaction = vaTransactionFor($user);
    // full_name tidak boleh kosong di database, jadi kosongkan lewat relasi.
    $transaction->setRelation('user', new User(['username' => 'tokopengantin']));

    fakeSnapTokenAndCreateVa();

    $result = app(BriService::class)->createVirtualAccount($transaction);

    expect($result['virtual_account_name'])->toBe('tokopengantin');
});

test('tanpa nama dan username, Virtual Account jatuh ke nama merchant', function () {
    $user = User::factory()->create();
    $transaction = vaTransactionFor($user);
    $transaction->setRelation('user', new User);

    fakeSnapTokenAndCreateVa();

    $result = app(BriService::class)->createVirtualAccount($transaction);

    // BRI menolak request tanpa nama, jadi harus ada nilai apa pun.
    expect($result['virtual_account_name'])->toBe('Anugerah Ahmad Fachrurochim');
});

test('nomor Virtual Account diawali partnerServiceId BRI', function () {
    $user = User::factory()->create(['full_name' => 'Andi Pratama']);

    fakeSnapTokenAndCreateVa();

    app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    $body = capturedCreateVaBody();

    expect($body['virtualAccountNo'])->toStartWith('8808')
        ->and($body['partnerServiceId'])->toBe('8808');
});

test('nomor Virtual Account berbeda antar transaksi', function () {
    $user = User::factory()->create(['full_name' => 'Andi Pratama']);
    $first = vaTransactionFor($user);
    $second = vaTransactionFor($user);

    $seen = [];

    Http::fake([
        '*/snap/v1.0/access-token/b2b' => Http::response(['accessToken' => 'fake-b2b-token'], 200),
        '*/snap/v1.0/transfer-va/create-va' => Http::response([
            'responseCode' => '2009000',
            'virtualAccountData' => ['virtualAccountNo' => '880800000000001'],
        ], 200),
    ]);

    $bri = app(BriService::class);
    $bri->createVirtualAccount($first);
    $bri->createVirtualAccount($second);

    foreach (Http::recorded() as $pair) {
        if (! str_contains($pair[0]->url(), '/transfer-va/create-va')) {
            continue;
        }
        $seen[] = json_decode($pair[0]->body(), true)['virtualAccountNo'];
    }

    // Nomor sama antar transaksi akan membuat webhook BRI tidak bisa memisahkan
    // pembayaran ketika dua orang membayar bersamaan.
    expect($seen)->toHaveCount(2)
        ->and($seen[0])->not->toBe($seen[1]);
});

test('rekening tujuan tetap rekening admin, bukan rekening pemesan', function () {
    $user = User::factory()->create(['full_name' => 'Maya Sari']);

    fakeSnapTokenAndCreateVa();

    $result = app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    // Nama VA = pemesan, rekening tujuan = admin. Dua hal berbeda, jangan tertukar.
    expect($result['virtual_account_name'])->toBe('Maya Sari')
        ->and($result['account_number'])->toBe('421201032041536')
        ->and($result['account_holder'])->toBe('Anugerah Ahmad Fachrurochim')
        ->and($result['virtual_account_no'])->not->toBe('421201032041536');
});

test('body request ke BRI adalah objek JSON, bukan string JSON', function () {
    $user = User::factory()->create(['full_name' => 'Rangga Saputra']);

    fakeSnapTokenAndCreateVa();

    app(BriService::class)->createVirtualAccount(vaTransactionFor($user));

    $raw = null;

    foreach (Http::recorded() as $pair) {
        if (str_contains($pair[0]->url(), '/transfer-va/create-va')) {
            $raw = $pair[0]->body();
        }
    }

    expect($raw)->not->toBeNull()
        // Body wajib diawali '{', bukan '"'.
        ->and(str_starts_with($raw, '{'))->toBeTrue()
        ->and(json_decode($raw, true))->toBeArray()
        ->and($raw)->toContain('"virtualAccountName":"Rangga Saputra"');
});

test('signature HMAC dihitung atas body yang benar-benar dikirim', function () {
    $user = User::factory()->create(['full_name' => 'Laras Wulandari']);

    fakeSnapTokenAndCreateVa();

    $bri = app(BriService::class);
    $bri->createVirtualAccount(vaTransactionFor($user));

    foreach (Http::recorded() as $pair) {
        if (! str_contains($pair[0]->url(), '/transfer-va/create-va')) {
            continue;
        }

        $sentBody = $pair[0]->body();
        $timestamp = $pair[0]->header('X-TIMESTAMP')[0] ?? null;

        // Signature BRI dihitung atas stringToSign yang memuat hash body.
        // Kalau body di-encode dua kali, hash yang dihitung tidak akan cocok
        // dengan body yang benar-benar ada di kabel.
        expect($timestamp)->not->toBeNull();

        $recomputed = $bri->snapSignature(
            'POST',
            '/snap/v1.0/transfer-va/create-va',
            'fake-b2b-token',
            $timestamp,
            $sentBody,
        );

        expect($recomputed)->toBe($pair[0]->header('X-SIGNATURE')[0] ?? null);
    }
});

test('nama Virtual Account ikut terbawa saat transaksi diserialisasi', function () {
    $user = User::factory()->create(['full_name' => 'Yoga Pratama']);
    $transaction = vaTransactionFor($user);

    // Accessor harus tetap jalan walau VA belum pernah dibuat.
    expect($transaction->virtual_account_name)->toBe('Yoga Pratama')
        ->and($transaction->toArray())->toHaveKey('virtual_account_name');
});

test('nama Virtual Account dari payload BRI mengalahkan nama user', function () {
    $user = User::factory()->create(['full_name' => 'Yoga Pratama']);
    $transaction = vaTransactionFor($user);

    $transaction->update([
        'metadata' => [
            'bri_va' => [
                'virtualAccountData' => ['virtualAccountName' => 'YOGA PRATAMA'],
            ],
        ],
    ]);

    // Nama yang benar-benar disimpan BRI harus yang ditampilkan, supaya
    // antarmuka tidak menampilkan nama basi.
    expect($transaction->fresh()->virtual_account_name)->toBe('YOGA PRATAMA');
});

test('nama Virtual Account yang dikirim BRI sama dengan yang tampil di API', function () {
    $user = User::factory()->create(['full_name' => 'Zulfikar Akbar']);

    fakeSnapTokenAndCreateVa();

    $result = app(BriService::class)->createVirtualAccount(vaTransactionFor($user));
    $sentToBri = capturedCreateVaBody()['virtualAccountName'];

    // Dua sumber ini harus selalu sepakat; kalau tidak, pengguna melihat
    // nama berbeda dari yang tertera di aplikasi BRI.
    expect($sentToBri)->toBe('Zulfikar Akbar')
        ->and($result['virtual_account_name'])->toBe($sentToBri);
});
