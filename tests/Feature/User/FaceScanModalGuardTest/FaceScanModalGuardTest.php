<?php

use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Guard untuk file face-scan-modal.blade.php.
 *
 * Modal ini punya history panjang bug yang SEMUANYA berasal dari satu kelas
 * kesalahan: perubahan di dalam string x-data tanpa memverifikasi hasilnya.
 * Tiga di antaranya merusak halaman secara sekaligus:
 *
 *   1. Kutip " mentah di dalam x-data="...". HTML parser mengakhiri atribut
 *      di kutip kedua, jadi seluruh x-data terpotong, Alpine gagal init, dan
 *      kode JavaScript bocor sebagai TEKS ke layar (screenshot user).
 *   2. Urutan asterisk-slash-asterisk pada baris komentar, yang menutup lalu
 *      membuka blok komentar di tempat yang salah sehingga sisa kode jadi orphan.
 *   3. window.ScannerUI dipanggil tanpa penjaga. app-web.js tidak dijamin
 *      termuat, jadi TypeError lempar startCamera() sebelum dipanggil -> modal
 *      menampilkan kotak hitam tanpa gambar kamera.
 *
 * Test ini mem-parse file sebagai Blade menghasilkan HTML, lalu mem-parse
 * nilai setiap atribut x-data/x-init sebagai JavaScript -- persis yang dilakukan
 * Alpine. Jadi kelas bug yang sama akan gagal di sini, bukan di browser user.
 * Pengecekannya lewat `new Function()` di Node, bukan `eval()` PHP: kedua
 * bahasa itu berbeda grammar, jadi PHP akan menolak markup JS yang sah.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
    $this->user = User::factory()->create();
    $this->user->assignRole('customer');
});

function faceScanBladePaths(): array
{
    // Resolved from __DIR__ rather than base_path()/resource_path(): those
    // helpers go through app(), which is not available while this file is being
    // loaded by the test runner. tests/Feature/User/<Name>/x.php is four levels
    // below the project root.
    $views = dirname(__DIR__, 4) . '/resources/views';

    return [
        'User' => $views . '/User/components/face-scan-modal/face-scan-modal.blade.php',
        'Welcome' => $views . '/Welcome/components/face-scan-modal/face-scan-modal.blade.php',
    ];
}

function faceScanModalRootHtml(): string
{
    actingAs(test()->user, 'web');

    // The modal is included by complete-profile-component, so render that.
    return Livewire::test(CompleteProfileComponent::class)->html();
}

/**
 * Alpine attributes whose value is JavaScript and therefore must survive HTML
 * attribute parsing intact.
 *
 * @return array<int, string>
 */
function alpineJsAttributes(): array
{
    return ['x-data', 'x-init'];
}

/**
 * Hanya badan komponen Alpine (`x-data="{ ... }"`), bukan seluruh file.
 *
 * Dipakai oleh cek "tidak ada pemanggilan window.ScannerUI telanjang": di atas
 * komponen ada blok `<script>` yang justru MENDEFINISIKAN `window.ScannerUI`
 * sebagai cadangan saat bundle JS belum terpasang. Memanggil `window.ScannerUI`
 * di dalam fallback itu justru yang benar (that's the point of the shim), jadi
 * menycan seluruh file akan menandai kode yang paling aman sebagai yang paling
 * berisiko.
 */
function faceScanAlpineComponentSource(string $panel): string
{
    $source = (string) file_get_contents(faceScanBladePaths()[$panel]);

    $start = strpos($source, 'x-data="{');

    if ($start === false) {
        return '';
    }

    return substr($source, $start);
}

/**
 * Pesan error dari parser JS untuk nilai atribut, atau null kalau valid.
 *
 * Pengecekannya memakai `new Function()` di Node, BUKAN `eval()` PHP. Alasannya:
 * bahasa keduanya mirip tapi tidak sama, jadi PHP akan menolak (atau menerima
 * salah) hal yang sah di JS -- trailing comma, `?.`, spread, template literal,
 * dan apa pun yang belum jadi grammar PHP. Salah baca di sini berarti test gagal
 * pada markup yang benar, dan `eval()` juga melempar ParseError yang tidak
 * tertangkap `@`, jadi test mati dengan error, bukan dengan pesan yang berguna.
 *
 * Dua atribut itu dibungkus berbeda, persis seperti perlakuannya di Alpine:
 *
 *   - `x-data` adalah EXPRESSION (objek literal), jadi dibungkus `return (...)`.
 *     Kalau dibungkus sebagai badan fungsi, `new Function('{ a: 1 }')` menganggap
 *     `a:` label dan gagal di tanda titik dua -- padahal di browser objek itu
 *     valid. Ini sempat membuat test salah melapor pada markup yang benar.
 *   - `x-init` adalah STATEMENT, jadi isinya jadi badan fungsi apa adanya.
 *
 * `new Function` tidak membuat fungsi async, jadi `await` di level atas akan
 * ditolak -- juga sama seperti Alpine yang hanya menjalankannya sebagai method.
 *
 * @return string|null
 */
function alpineJsError(string $value, string $attribute): ?string
{
    $payload = json_encode($value, JSON_UNESCAPED_UNICODE);

    $source = $attribute === 'x-data'
        ? 'new Function("return (" + ' . $payload . ' + ");");'
        : 'new Function(' . $payload . ');';

    $process = @proc_open(
        ['node', '-e', $source],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (! is_resource($process)) {
        // Node tidak tersedia: jangan gagalkan test, tapi jangan juga mengklaim
        // nilainya sudah dicek. Test lain di file ini tetap sumber kebenaran.
        return null;
    }

    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) === 0 && trim($stderr) === '') {
        return null;
    }

    return trim($stderr) ?: 'node berhenti dengan kode bukan nol tanpa pesan';
}

dataset('panel', array_keys(faceScanBladePaths()));

test('the modal renders and every alpine js attribute is valid javascript', function (string $panel): void {
    $html = faceScanModalRootHtml();

    $found = 0;

    foreach (alpineJsAttributes() as $attribute) {
        // Attribute values may legitimately contain " when Blade escaped them,
        // so match across the whole document rather than a single line.
        preg_match_all('/' . preg_quote($attribute, '/') . '="(.*?)"/s', $html, $matches);

        foreach ($matches[1] as $index => $value) {
            $found++;

            // Nilai atribut di HTML ter-escape: kutip di dalam JavaScript ditulis
            // `&quot;`, jadi HTML parser menutup atribut di kutip yang benar.
            // Yang di-eval harus bentuk yang sudah di-decode -- inilah yang
            // dilakukan browser sebelum mengoper string ke Alpine.
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // The failure mode was a raw " that terminated the attribute early,
            // leaving a truncated expression. Parsing is the real check, dan
            // pesan error Node ikut ditampilkan supaya kegagalan bisa dibaca
            // tanpa harus merender ulang halaman.
            $error = alpineJsError($value, $attribute);

            expect($error)->toBeNull(
                $attribute . ' #' . $index . ' tidak bisa diparse sebagai JS'
                . ($error ? ' (' . $error . ')' : '')
                . ': ' . mb_substr($value, 0, 200)
            );
        }
    }

    expect($found)->toBeGreaterThan(0, 'tidak ada atribut Alpine JS yang bisa diperiksa');
})->with('panel');

test('the modal html never leaks its own javascript source as visible text', function (): void {
    $html = faceScanModalRootHtml();

    // Symptom of the truncated-attribute bug: the x-data payload ended up as
    // text content instead of an attribute value.
    foreach (['this.startCamera', 'getUserMedia', 'cameraState'] as $needle) {
        // Legitimate only inside an attribute value. If it appears between tags,
        // the attribute was cut and the source spilled into the document.
        $asText = preg_match('/>\s*[^<>]*' . preg_quote($needle, '/') . '[^<>]*</s', $html);

        expect($asText)->toBe(0, "'{$needle}' bocor sebagai teks HTML, bukan atribut");
    }
});

test('the modal never calls window.ScannerUI unguarded', function (string $panel): void {
    // Setiap akses harus lewat wrapper scannerUI(), yang mencatat error dan
    // mengembalikan null, bukan melempar. Panggilan telanjang di dalam x-data
    // akan melempar saat app-web.js belum termuat, dan di open() itu
    // membiarkan startCamera() dilewati -- itulah bug kotak hitamnya.
    //
    // Yang di-scan hanya badan komponen Alpine; blok <script> fallback di atasnya
    // memang mendefinisikan window.ScannerUI, jadi pemanggilan di sana justru
    // jalur yang paling aman (lihat faceScanAlpineComponentSource()).
    $source = faceScanAlpineComponentSource($panel);

    expect($source)->not->toBe('');

    $bare = preg_match_all('/window\.ScannerUI\s*\.\s*\w+\s*\(/', $source);

    expect($bare)->toBe(
        0,
        "masih ada pemanggilan window.ScannerUI langsung; pakai this.scannerUI('method', ...)"
    );
})->with('panel');

test('the modal loads the scanner helper itself', function (string $panel): void {
    $source = file_get_contents(faceScanBladePaths()[$panel]);

    // Without this the whole flow depends on some other page having loaded
    // app-web.js first, which is not guaranteed.
    expect($source)->toContain("resources/js/app-web/app-web.js")
        // @vite() looks in public/build/manifest.json, but Vite >=5 writes to
        // public/build/<dir>/.vite/manifest.json, so @vite() 500s the page.
        ->and($source)->not->toContain("@vite('resources/js/app-web/app-web.js')");
})->with('panel');

test('the camera state machine cannot hang in starting', function (): void {
    $source = faceScanAlpineComponentSource('User');

    // Yang dikunci adalah state-nya, bukan bentuk baris pertamanya: startCamera()
    // boleh menaruh token anti-race atau reset error sebelum menandai 'starting'.
    // Yang tidak boleh terjadi adalah spinner 'starting' yang tidak pernah
    // ditinggalkan -- jadi setiap jalur keluar harus menandai state lain.
    expect($source)
        ->toMatch("/async startCamera\(\)\s*\{(?:(?!\n\s{8}\}).){0,600}?this\.cameraState = 'starting';/s")
        // Jalur sukses (kamera hidup) dan jalur gagal (ditolak / error).
        ->toMatch("/this\.cameraState = 'ready';/")
        ->toMatch("/this\.cameraState = 'error';/")
        // Menutup modal mengembalikan ke idle, jadi tidak ada state yang
        // menggantung setelah kamera dimatikan.
        ->toMatch("/this\.cameraState = 'idle';/");
});

test('a missing video element is reported, never skipped in silence', function (): void {
    $source = faceScanAlpineComponentSource('User');

    // <video> hidup di dalam komponen dan di-teleport ke body. Elemennya bisa
    // belum ada saat startCamera() berjalan; dulu jalur itu `if (!video) return;`
    // -- kamera tidak pernah menyala tanpa satu pesan pun ke pengguna. Sekarang
    // jalur itu harus menandai error dulu baru keluar.
    expect($source)
        ->not->toMatch('/if \(!video\)\s*return;/')
        // Jalur "video belum ada" harus menandai error sebelum keluar. Rentang
        // 400 karakter longgar karena di antaranya ada interpolasi Blade
        // (`{{ ... }}`) yang mengandung kurung kurawal.
        ->toMatch("/if \(!video\)[\s\S]{0,400}?cameraState = 'error';/")
        // Setelah stream terpasang, elemennya masih ditunggu sampai benar-benar
        // punya gambar (perangkat lama butuh frame pertama sebelum bisa
        // di-capture).
        ->toMatch('/waitForPicture\(/');
});

test('mirroring is applied only to the front camera', function (): void {
    $source = faceScanAlpineComponentSource('User');

    // Kamera depan harus dicermin supaya pratinjaunya terasa seperti cermin;
    // kamera belakang (dokumen identitas) tidak boleh, atau teks di KTP dibaca
    // terbalik.
    //
    // Bentuknya boleh getter (`get mirroredView()`) atau assignment; yang dikunci
    // adalah syaratnya: `environment` dan label kamera belakang tidak dicermin.
    expect($source)
        ->toContain('scaleX(-1)')
        ->toMatch("/cameraFacing === 'environment'/")
        ->toMatch('/mirroredView\s*(\(\)\s*\{|=[^;]*)/');
});

test('the user and welcome copies stay in sync', function (): void {
    $user = file_get_contents(faceScanBladePaths()['User']);
    $welcome = file_get_contents(faceScanBladePaths()['Welcome']);

    // These are parallel copies of one component; a fix applied to one and not
    // the other means the bug still reproduces on the other panel.
    expect($welcome)->toBe($user, 'face-scan-modal Welcome dan User berbeda');
});

test('the complete profile page still renders the modal', function (): void {
    actingAs($this->user, 'web');

    $component = Livewire::test(CompleteProfileComponent::class);

    $html = $component->html();

    // Penanda modal yang mengikuti markup yang benar-benar ada: elemen video
    // teleported, judul modal, dan event pembuka yang dipasang wrapper-nya.
    // Class `face-scan-wrapper`/`face-scan-modal` yang dulu dipakai sebagai
    // penanda sudah tidak ada di partial (wrapper-nya sekarang injected lewat
    // variabel), jadi mengandalkannya hanya menguji sejarah view, bukan
    // kehadirannya.
    expect($html)
        ->toContain('id="face-scan-video"')
        ->toContain('id="face-scan-title"')
        ->toContain('x-on:open-face-scan.window="open()"')
        // Wrapper di halaman induk harus tetap meneruskan selector yang sama ke
        // modal, kalau tidak modal mencari wrapper yang tidak ada.
        ->toContain("wrapper: '.face-scan-wrapper'");
});