<?php

/*
 * Teks komentar Blade tidak boleh bocor ke halaman.
 *
 * Bug yang dikunci di sini nyata dan baru saja terjadi. Untuk "menonaktifkan"
 * blok markup Sign Up, teks komentarnya sendiri menulis literal pembuka dan
 * penutup komentar Blade. Blade TIDAK mendukung komentar bersarang: penutup yang
 * tertulis di dalam teks komentar langsung menutup komentar lebih awal, lalu
 * seluruh sisa paragraf bocor ke HTML dan tampil sebagai teks besar di bawah
 * form -- terlihat di /user/signin dan /user/auth.
 *
 * Yang diperiksa dua lapis, karena sumber dan akibatnya bisa lolos terpisah:
 *
 *   1. SUMBER: tidak ada pembuka komentar Blade yang muncul DI DALAM badan
 *      komentar yang sedang terbuka. Simulasi parser-nya (buka di `{{--`,
 *      tutup di `--}}` berikutnya, dibaca kiri ke kanan seperti Blade) dipakai
 *      karena itu hanya sidik jari statis yang bisa dipercaya: `--}}` di dalam
 *      teks komentar tidak bisa dibedakan dari penutup yang memang dimaksud --
 *      Blade memperlakukannya sama, dan itulah justru bug-nya. Yang bisa
 *      dideteksi adalah pembuka kedua, dan komentar yang tidak pernah ditutup
 *      sampai akhir file.
 *   2. AKIBAT: HTML hasil render tidak boleh memuat kata watchdog dari blok
 *      yang dikomentari. Ini jaminan yang sesungguhnya; yang di atas cuma
 *      penanda agar penyebabnya terlihat saat diff dibaca.
 *
 * Kenapa test terpisah, bukan assertion tambahan di test auth yang sudah
 * ada: partial yang bocor dirender oleh /user/signin DAN /user/auth,
 * sementara test lain fokus pada hal lain (breadcrumb, tombol, label).
 * Tidak ada yang memeriksa "teks yang tak seharusnya tidak muncul di layar".
 *
 * Dua partial ikut diperiksa karena keduanya me-render blok Sign Up dengan
 * bentuk yang sama: yang User dipakai halaman auth, yang Welcome dipakai
 * storefront.
 */

use App\Filament\User\Auth\Auth\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function commentedAuthPartials(): array
{
    $views = dirname(__DIR__, 4).'/resources/views';

    return [
        'User' => $views.'/User/social-buttons/social-buttons/social-buttons.blade.php',
        'Welcome' => $views.'/Welcome/social-buttons/social-buttons/social-buttons.blade.php',
    ];
}

dataset('partial', array_keys(commentedAuthPartials()));

test('no comment marker is written inside a comment body', function (string $partial): void {
    $path = commentedAuthPartials()[$partial];
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    expect($lines)->toBeArray();

    $inside = false;
    $openedAt = 0;

    foreach ($lines as $index => $line) {
        $cursor = 0;
        $length = strlen($line);

        // Dipindai dari kiri ke kanan, bukan per baris utuh: Blade membaca
        // seluruh file sebagai satu stream, jadi penutup komentar boleh jatuh
        // di baris mana saja dan satu baris bisa jadi lebih dari satu kejadian.
        while ($cursor < $length) {
            $open = strpos($line, '{{--', $cursor);
            $close = strpos($line, '--}}', $cursor);

            if (! $inside) {
                if ($open === false) {
                    break;
                }

                $inside = true;
                $openedAt = $index + 1;
                $cursor = $open + 4;

                continue;
            }

            // Sedang di dalam komentar. Pembuka kedua di sini adalah penanda
            // kode yang sedang mendokumentasikan delimiter-nya.
            if ($open !== false && ($close === false || $open < $close)) {
                test()->fail(
                    "Baris " . ($index + 1) . " di {$partial} menulis pembuka komentar Blade "
                    ."di dalam badan komentar yang dibuka di baris {$openedAt}. Penanda itu akan "
                    .'ditutup lebih awal oleh penutup yang tertulis di baris yang sama, dan sisa '
                    .'teksnya bocor ke halaman sebagai teks terlihat. Tulis kata '
                    .'"pembungkus komentar", bukan simbolnya.'
                );
            }

            if ($close === false) {
                break;
            }

            $inside = false;
            $cursor = $close + 4;
        }
    }

    expect($inside)->toBeFalse(
        "Komentar Blade yang dibuka di baris {$openedAt} di {$partial} tidak pernah ditutup sampai "
        .'akhir file. Blade akan memperlakukan seluruh sisa file sebagai komentar.'
    );
})->with('partial');

test('the auth landing page shows no commented-out sign up text', function (): void {
    // Lapis kedua: yang diuji akibatnya, bukan sumbernya.
    $html = get(Auth::getUrl())
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('showAuthSwitchLink')
        ->not->toContain('DIMATIKAN dengan sengaja')
        ->not->toContain('Daftar dengan Google')
        ->not->toContain('Belum memiliki akun?');
});

test('the sign in form shows no commented-out sign up text', function (): void {
    $html = get('/user/signin')
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('showAuthSwitchLink')
        ->not->toContain('DIMATIKAN dengan sengaja')
        ->not->toContain('Belum memiliki akun?');
});

test('the sign up markup is still in the source, just commented out', function (): void {
    // Penyeimbang dua test di atas: "tidak bocor" tidak boleh tercapai dengan
    // menghapus bloknya. Kodenya harus masih ada di file, hanya tidak dirender.
    foreach (commentedAuthPartials() as $partial => $path) {
        $source = (string) file_get_contents($path);

        expect($source)
            // Flag penghidupkannya masih ada.
            ->toContain('$showAuthSwitchLink')
            // Markup aslinya masih utuh di dalam komentar.
            ->toContain("__('Belum memiliki akun?')")
            ->toContain("__('Sudah memiliki akun?')")
            ->toContain('@if ($authMode === \'signup\')');
    }
});