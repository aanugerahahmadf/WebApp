<?php

use App\Services\GoogleAppsScript\AppsScriptSignature;
use App\Services\GoogleAppsScript\GoogleAppsScriptClient;
use App\Services\GoogleAppsScript\AppsScriptException;
use App\Jobs\PushToAppsScript\PushToAppsScript;
use App\Models\Review\Review;
use App\Support\AppsScriptMirror\AppsScriptMirror;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Kontrak antara Laravel dan Web App Google Apps Script.
 *
 * Golden vector di bawah TIDAK dibuat dari kode PHP. Angka-angkanya dihasilkan
 * oleh `canonicalJson_()`/`sign_()` di apps_script/Auth.gs dan dicocokkan lewat
 * `node apps_script/tests/contract.test.js` + pembanding lintas bahasa. Artinya
 * test ini benar-benar mengunci "PHP dan Apps Script menghitung hal yang sama",
 * bukan sekadar menguji fungsi terhadap dirinya sendiri.
 *
 * Empat hal yang pernah/berpotensi pecah diam-diam:
 *
 * 1. Apps Script selalu membalas HTTP 200. Klien yang menilai `$response->failed()`
 *    akan menganggap penolakan signature sebagai sukses.
 * 2. Secret tidak boleh lewat header — Web App tidak bisa membacanya.
 * 3. Nonce sekali pakai. Retry dengan nonce lama = `replayed_nonce` palsu.
 * 4. Nominal sebagai float membuat PHP mengirim `150000.0` sementara JS
 *    `150000` -> tanda tangan beda, semua tulis ditolak.
 */
const APPS_SCRIPT_SECRET = 'cross-language-secret';

const APPS_SCRIPT_VECTORS = [
    'a' => [
        'payload' => ['order_id' => 42, 'total_amount' => '150000', 'status' => 'pending'],
        'canonical' => '{"order_id":42,"status":"pending","total_amount":"150000"}',
        'signature' => 'ac7e1adfde11e8d189a68582c28c130750cef4c01ab26584d7b02332f8815b85',
    ],
    'b' => [
        'payload' => ['records' => [['z' => 1, 'a' => 2], ['y' => true, 'x' => null]], 'keyColumn' => 'order_number'],
        'canonical' => '{"keyColumn":"order_number","records":[{"a":2,"z":1},{"x":null,"y":true}]}',
        'signature' => 'ff3b9ed08a9cfa439aebba8845c65b973878f8666fbb1c96b91321df4a29fc27',
    ],
    'c' => [
        'payload' => [],
        'canonical' => '{}',
        'signature' => '191cee0bb3119774a7aeb0c51b44467db353c1f5871ba816b8203eaeb19b27b2',
    ],
    'd' => [
        'payload' => ['nama' => 'Bunga Mawar & Melati', 'note' => "baris\nbaru", 'path' => '/storage/a/b.jpg'],
        // JSON.stringify di JS dan json_encode di PHP sama-sama menuliskan
        // newline sebagai DUA karakter (backslash + n), bukan baris baru asli.
        'canonical' => '{"nama":"Bunga Mawar & Melati","note":"baris\\nbaru","path":"/storage/a/b.jpg"}',
        'signature' => '431213a5d61d633d851a8cc9d6a6e5a50a71d6a0a4d3bcd0ec8beb0adf478d59',
    ],
    'e' => [
        'payload' => ['amount' => 150000.0, 'rate' => 4.5, 'nested' => ['deep' => ['b' => 1, 'a' => 2]]],
        'canonical' => '{"amount":150000,"nested":{"deep":{"a":2,"b":1}},"rate":4.5}',
        'signature' => 'e86cc15ceb3f07a000de0e2c0f4f982307cc9bb58dea62536addb53e70b4c3da',
    ],
    'f' => [
        'payload' => ['id' => '0012345678', 'empty_list' => [], 'tags' => ['b', 'a']],
        'canonical' => '{"empty_list":{},"id":"0012345678","tags":["b","a"]}',
        'signature' => '545375d00c92b69a30351c79221c4350ab9c5613b4cef037a2b5def347955ae3',
    ],
];

function appsScriptConfig(array $overrides = []): void
{
    config([
        'services.google_apps_script' => array_merge([
            'enabled' => true,
            'exec_url' => 'https://script.google.com/macros/s/TEST_DEPLOYMENT/exec',
            'secret' => APPS_SCRIPT_SECRET,
            'read_key' => 'read-key-under-test',
            'source' => 'laravel',
            'timeout' => 5,
            'attempts' => 2,
            // Sama dengan default config/services.php. Helper ini menimpa
            // SELURUH blok, jadi kunci `mirror` wajib ikut disebut -- kalau
            // tidak, AppsScriptMirror::enabled() selalu false dan test gating
            // akan lulus tanpa alasan.
            'mirror' => ['orders' => true, 'reviews' => true, 'catalog' => false, 'kyc' => true],
        ], $overrides),
    ]);
}

/** Body yang dikirim klien, sudah di-JSON-encode lalu dibaca kembali sebagai array. */
function appsScriptBody(Request $request): array
{
    return json_decode($request->body(), true) ?: [];
}

beforeEach(function () {
    appsScriptConfig();

    // BUKAN `Http::fake(['*' => ...])`: stub Http menumpuk dan yang mendaftar
    // lebih dulu yang menang, jadi default di sini akan menutupi stub tiap test
    // dan semua assertion respons jadi palsu. preventStrayRequests() memastikan
    // tidak ada request nyata keluar tanpa merampas stub milik test.
    Http::preventStrayRequests();
});

/* ------------------------------------------------------------------ 1. signature */

it('menghasilkan canonical JSON dan HMAC yang sama dengan Apps Script', function (string $nonce) {
    $vector = APPS_SCRIPT_VECTORS[$nonce];

    expect(AppsScriptSignature::canonicalJson($vector['payload']))->toBe($vector['canonical']);
    expect(AppsScriptSignature::sign(1700000000, $nonce, 'orders.rows.append', $vector['payload'], APPS_SCRIPT_SECRET))
        ->toBe($vector['signature']);
})->with(array_keys(APPS_SCRIPT_VECTORS));

it('menormalkan nominal float menjadi integer agar cocok dengan JS', function () {
    // json_encode(150000.0) = "150000.0" sementara String(150000) di JS = "150000".
    expect(AppsScriptSignature::canonicalJson(['total' => 150000.0]))->toBe('{"total":150000}');
});

it('mengurutkan kunci bersarang secara rekursif', function () {
    $left = AppsScriptSignature::canonicalJson(['b' => 1, 'a' => ['z' => 1, 'y' => ['q' => 1, 'p' => 2]]]);
    $right = AppsScriptSignature::canonicalJson(['a' => ['y' => ['p' => 2, 'q' => 1], 'z' => 1], 'b' => 1]);

    expect($left)->toBe($right)->and($left)->toBe('{"a":{"y":{"p":2,"q":1},"z":1},"b":1}');
});

it('memperlakukan array kosong sebagai objek karena payload selalu objek', function () {
    // body dikirim dengan cast (object); kanonisasi harus menghasilkan '{}' juga.
    expect(AppsScriptSignature::canonicalJson([]))->toBe('{}');
});

it('mempertahankan unicode, slash, dan escape control character seperti JS', function () {
    $canonical = AppsScriptSignature::canonicalJson(['kata' => 'Mawar & Melati', 'url' => '/storage/x.jpg']);

    expect($canonical)->toBe('{"kata":"Mawar & Melati","url":"/storage/x.jpg"}');
    expect(AppsScriptSignature::canonicalJson(['n' => "a\nb"]))->toBe('{"n":"a\\nb"}');
});

/* ------------------------------------------------------------------ 2. request */

it('mengirim kredensial di body, bukan di header', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'data' => ['written' => 1]])]);

    GoogleAppsScriptClient::make()->append('orders.rows', ['order_number' => 'INV-1']);

    Http::assertSent(function (Request $request) {
        $body = appsScriptBody($request);

        expect($request->url())->toBe('https://script.google.com/macros/s/TEST_DEPLOYMENT/exec');
        expect($request->header('Authorization'))->toBeArray()->toHaveCount(0);

        return $body['source'] === 'laravel'
            && $body['action'] === 'orders.rows.append'
            && isset($body['timestamp'], $body['nonce'], $body['signature'])
            && strlen($body['signature']) === 64
            && $body['payload']['records'] === [['order_number' => 'INV-1']];
    });
});

it('menandatangani persis payload yang dikirim di body', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'data' => ['written' => 1]])]);

    $payload = ['order_number' => 'INV-7', 'total_amount' => '250000', 'status' => 'pending'];
    GoogleAppsScriptClient::make()->append('orders.rows', $payload);

    Http::assertSent(function (Request $request) {
        $body = appsScriptBody($request);
        $recomputed = AppsScriptSignature::sign(
            (int) $body['timestamp'],
            $body['nonce'],
            $body['action'],
            $body['payload'],
            APPS_SCRIPT_SECRET,
        );

        // Kalau kedua sisi tidak sepakat soal bentuk payload, Apps Script akan
        // membalas bad_signature saat runtime -- di sinilah itu ketahuan.
        return $recomputed === $body['signature'];
    });
});

it('memakai nonce baru pada percobaan ulang', function () {
    $seen = [];
    $calls = 0;

    Http::fake(function (Request $request) use (&$calls, &$seen) {
        $body = json_decode($request->body(), true) ?: [];
        $seen[] = $body['nonce'] ?? null;
        $calls++;

        if ($calls === 1) {
            throw new ConnectionException('koneksi terputus');
        }

        return Http::response(['ok' => true, 'data' => ['written' => 1]]);
    });

    $result = GoogleAppsScriptClient::make()->append('orders.rows', ['order_number' => 'INV-1']);

    // Nonce lama sudah tercatat di CacheService Apps Script, jadi mengirim
    // ulang dengan nonce yang sama akan berbunyi `replayed_nonce` palsu.
    expect($result)->toBe(['written' => 1])
        ->and($seen)->toHaveCount(2)
        ->and($seen[0])->not->toBe($seen[1]);
});

it('meloloskan payload batch besar dalam satu chunk tapi memotong di atas 500', function () {
    $records = array_fill(0, 600, ['order_number' => 'INV']);

    expect(GoogleAppsScriptClient::chunks($records))->toHaveCount(2)
        ->and(GoogleAppsScriptClient::chunks($records)[0])->toHaveCount(500);
});

/* ------------------------------------------------------------------ 3. response */

it('membaca keberhasilan dari field ok meski status HTTP 200', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'error' => ['code' => 'bad_signature', 'message' => 'HMAC tidak cocok']], 200)]);

    expect(fn () => GoogleAppsScriptClient::make()->append('orders.rows', ['x' => 1]))
        ->toThrow(AppsScriptException::class, 'HMAC tidak cocok');
});

it('memetakan kode error Apps Script ke exception yang bisa dibedakan', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'requestId' => 'r1', 'error' => ['code' => 'stale_request', 'message' => 'jam meleset']], 200)]);

    try {
        GoogleAppsScriptClient::make()->push('orders.rows.append', []);
        $this->fail('seharusnya melempar');
    } catch (AppsScriptException $exception) {
        expect($exception->errorCode())->toBe('stale_request')
            ->and($exception->isSignatureProblem())->toBeTrue()
            ->and($exception->isConfigurationProblem())->toBeFalse()
            ->and($exception->requestId())->toBe('r1');
    }
});

it('menolak respons HTML login Google dengan pesan yang menjelaskan penyebabnya', function () {
    // Gejala klasik: deployment "Only me", atau URL memakai /dev bukan /exec.
    Http::fake(['*' => Http::response('<html><body>Sign in</body></html>', 200, ['Content-Type' => 'text/html'])]);

    try {
        GoogleAppsScriptClient::make()->push('health.ping');
        $this->fail('seharusnya melempar');
    } catch (AppsScriptException $exception) {
        expect($exception->errorCode())->toBe('bad_envelope')
            ->and($exception->isConfigurationProblem())->toBeTrue()
            ->and($exception->getMessage())->toContain('/exec');
    }
});

it('pushQuietly tidak pernah melempar di jalur request pengguna', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'error' => ['code' => 'handler_error', 'message' => 'sheet penuh']], 200)]);

    $result = GoogleAppsScriptClient::make()->pushQuietly('orders.rows.append', ['order_number' => 'INV-1']);

    expect($result)->toBeArray()
        ->and($result['ok'])->toBeFalse()
        ->and($result['code'])->toBe('handler_error');
});

it('health check mengembalikan not_configured tanpa memanggil jaringan', function () {
    appsScriptConfig(['exec_url' => '', 'secret' => '']);

    expect(GoogleAppsScriptClient::make()->health())->toBe(['ok' => false, 'code' => 'not_configured']);
    Http::assertNothingSent();
});

it('menolak memakai URL http biasa', function () {
    appsScriptConfig(['exec_url' => 'http://script.google.com/macros/s/X/exec']);

    expect(GoogleAppsScriptClient::make()->isConfigured())->toBeFalse();
});

it('mendeteksi secret kosong sebagai belum terpasang', function () {
    appsScriptConfig(['secret' => '']);

    expect(GoogleAppsScriptClient::make()->isConfigured())->toBeFalse();
});

/* ------------------------------------------------------------------ 4. read */

it('memakai read key untuk aksi baca tanpa HMAC', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'data' => ['rows' => [['order_number' => 'INV-1']], 'total' => 1]])]);

    $rows = GoogleAppsScriptClient::make()->list('orders.rows', ['status' => 'pending']);

    expect($rows['total'])->toBe(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'action=records.list')
        && str_contains($request->url(), 'key=read-key-under-test')
        && $request->method() === 'GET');
});

it('mengambil remote config klien', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'data' => ['flags' => ['maintenance_banner' => 'Libur nasional', 'visual_search_enabled' => true]]])]);

    expect(GoogleAppsScriptClient::make()->flags())
        ->toBe(['maintenance_banner' => 'Libur nasional', 'visual_search_enabled' => true]);
});

/* ------------------------------------------------------------------ 5. job */

it('job melewati eksekusi diam-diam saat integrasi dimatikan', function () {
    appsScriptConfig(['secret' => '']);

    PushToAppsScript::dispatchSync('orders.rows.append', ['order_number' => 'INV-1']);

    Http::assertNothingSent();
});

it('job tidak me-retry kegagalan tanda tangan dan tidak menelannya diam-diam', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'error' => ['code' => 'bad_signature', 'message' => 'secret beda']], 200)]);

    $job = new PushToAppsScript('orders.rows.append', ['order_number' => 'INV-1'], 'order #1');

    expect(fn () => $job->handle())->toThrow(AppsScriptException::class);

    // Satu percobaan saja: mengirim ulang dengan nonce baru tetap akan ditolak
    // selama secret-nya salah, jadi retry hanya membakar kuota Apps Script.
    Http::assertSentCount(1);
});

/* ------------------------------------------------------------------ 6. mirror */

it('tidak memasang pendengar model sama sekali saat integrasi mati', function () {
    // Default .env: enabled=false. Checkout tidak boleh mendapat job queue baru
    // sebelum operator benar-benar men-deploy Apps Script.
    appsScriptConfig(['enabled' => false]);

    expect(AppsScriptMirror::enabled('orders'))->toBeFalse()
        ->and(AppsScriptMirror::enabled('reviews'))->toBeFalse();

    AppsScriptMirror::boot();

    // Tidak ada model yang tersimpan di test ini, tapi kalau boot() nekat
    // dispatch, Http::preventStrayRequests() dari beforeEach akan meledak.
    Http::assertNothingSent();
});

it('menghormati sakelar per modul ketika integrasi hidup', function () {
    appsScriptConfig(['enabled' => true]);

    expect(AppsScriptMirror::enabled('orders'))->toBeTrue()
        ->and(AppsScriptMirror::enabled('reviews'))->toBeTrue()
        // Katalog jarang berubah dan volumenya besar: default memang mati.
        ->and(AppsScriptMirror::enabled('catalog'))->toBeFalse()
        ->and(AppsScriptMirror::enabled('kyc'))->toBeTrue()
        ->and(AppsScriptMirror::enabled('modul-tidak-ada'))->toBeFalse();
});

it('job tidak pernah memuat secret atau payload di label log', function () {
    $job = new PushToAppsScript('orders.rows.append', ['qris_payload' => '00020126...', 'va_number' => '8808001234'], 'order #42');

    // `label` adalah satu-satunya teks yang boleh masuk log: ia menyebut nomor
    // order, bukan nomor VA, QRIS, atau telepon pelanggan (AGENTS.md §2.5).
    expect($job->label)->toBe('order #42')
        ->and($job->action)->toBe('orders.rows.append');
});

it('menghilangkan nilai null dari payload supaya sheet tidak berisi tembok nol', function () {
    $review = new Review;
    $review->id = 7;
    $review->rating = 5;
    $review->comment = 'Bagus';
    $review->catalog_id = null;

    $payload = AppsScriptMirror::reviewPayload($review);

    expect($payload)->toHaveKey('review_id')
        ->and(array_keys($payload))->not->toContain('catalog_id')
        ->and($payload['source'])->toBe('laravel');
});
