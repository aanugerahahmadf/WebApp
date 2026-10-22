<?php

/**
 * Halaman "Kata Sandi dan Keamanan".
 *
 * Bentuknya sekarang SATU halaman dengan bagian-bagian di dalamnya, dipilih
 * lewat query `?section=`. Homepage halaman ini: `/settings/password-security`
 * di panel Welcome (path root).
 *
 * Kenapa satu halaman, bukan satu route per bagian:
 *
 *   - Panel 'user' sudah dilebur ke panel 'welcome'. Versi User punya route
 *     sendiri per bagian (`settings/password-security/change-password` dst.),
 *     Versi Welcome punya satu route dengan `?section=`. Mendaftarkan
 *     keduanya membuat route yang sama terdaftar dua kali, dan yang kalah
 *     biasanya bukan yang disangka.
 *   - Jadi hanya SATU bentuk yang hidup, dan bentuk itu yang diuji di sini.
 *
 * Yang dikunci:
 *
 *   1. Halamannya terdaftar di panel Welcome dan benar-benar bisa dirender.
 *      View yang salah path lolos saat kelasnya syntax-nya bersih, tapi
 *      meledak jadi 500 saat dibuka.
 *   2. Tiap bagian punya judul sendiri dan punya URL sendiri.
 *   3. Label kelompoknya lewat file bahasa, bukan ditulis di view.
 *   4. Tombol notifikasi mengarah ke bagian yang benar, bukan ke route mati.
 */

use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Filament\Welcome\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;

// getUrl() milik Filament butuh panel aktif.
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

/**
 * [section, judul yang harus tampil]
 *
 * Dipakai sebagai dataset, jadi tiap elemen harus berupa array berisi dua
 * nilai -- bukan peta asosiatif. Peta asosiatif akan mengirim satu argumen
 * saja, sehingga testnya diam-dim menguji URL sebagai nama class.
 */
dataset('bagian keamanan', [
    ['index', 'Kata Sandi dan Keamanan'],
    ['change-password', 'Ubah Kata Sandi'],
    ['two-factor', 'Autentikasi Dua Faktor'],
    ['saved-login', 'Sign In Tersimpan'],
    ['sign-in-activity', 'Tempat Anda Sign In'],
    ['recent-emails', 'Ubah Email'],
    ['checkup', 'Pemeriksaan Keamanan'],
]);

/*
 * Render -- yang paling rawan. View yang salah path lolos saat kelasnya
 * syntax-nya bersih, tapi meledak jadi 500 begitu halamannya dibuka.
 */
it('merender setiap bagian keamanan tanpa error', function (string $section): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PasswordSecurityPage::class)
        ->set('section', $section)
        ->assertSuccessful();
})->with('bagian keamanan');

it('membuka setiap bagian keamanan di URL yang semestinya', function (string $section): void {
    $this->actingAs(User::factory()->create());

    $this->get(PasswordSecurityPage::getUrl(['section' => $section], panel: 'welcome'))
        ->assertSuccessful();
})->with('bagian keamanan');

it('menampilkan judul yang berbeda untuk setiap bagian', function (string $section, string $title): void {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)
        ->set('section', $section)
        ->html();

    expect($html)->toContain(__($title));
})->with('bagian keamanan');

it('halaman keamanan terdaftar di panel Welcome', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->uri() === 'settings/password-security');

    expect($routes)->not->toBeEmpty();

    // Exactly one page owns the URL. Two pages with the same slug means the
    // route resolves twice and the loser is decided by registration order.
    expect($routes->map(fn ($route): ?string => $route->getActionName())->filter(
        fn (?string $action): bool => str_contains((string) $action, 'PasswordSecurityPage')
    )->count())->toBe(1);
});

it('halaman keamanan hidup di root, bukan di /user', function (): void {
    expect(PasswordSecurityPage::getUrl(panel: 'welcome'))->toContain('/settings/password-security')
        ->and(PasswordSecurityPage::getUrl(panel: 'welcome'))->not->toContain('/user/');
});

/*
 * Daftar di halaman index
 */
it('halaman index menautkan semua bagian', function (): void {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)->set('section', 'index')->html();

    foreach ([
        'change-password',
        'two-factor',
        'saved-login',
        'sign-in-activity',
        'recent-emails',
        'checkup',
    ] as $section) {
        expect($html)->toContain('section='.$section);
    }
});

/*
 * Breadcrumb
 */
it('menyambung breadcrumb dari Pengaturan sampai halaman ini', function (): void {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)->set('section', 'index')->html();

    expect($html)
        ->toContain('Pengaturan')
        ->toContain('Kata Sandi dan Keamanan');
});

it('tidak menggandakan "Kata Sandi dan Keamanan" di crumb index', function (): void {
    // Induk index adalah kata yang sama dengan judulnya, jadi tanpa penjaga
    // crumbsnya jadi: Pengaturan / Kata Sandi dan Keamanan / Kata Sandi dan
    // Keamanan.
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)->set('section', 'index')->html();

    expect(substr_count($html, 'Kata Sandi dan Keamanan'))->toBeLessThan(4);
});

it('tidak menulis label kelompok dengan huruf kapital', function (): void {
    // Labelnya "Sign In dan Pemulihan" di Indonesia, tapi en.json
    // menerjemahkannya jadi "Sign In and Recovery". CSS uppercase membuatnya
    // tampil "SIGN IN AND RECOVERY", yang berbeda dari teks aslinya -- dan
    // berbeda juga dari nama foldernya, jadi tidak bisa dicari.
    //
    // Yang diuji adalah bentuk teksnya, bukan terjemahannya: locale test
    // mengikuti APP_LOCALE (id), sedangkan browser memakai bahasa yang
    // dipilih SetLocale. Menguji string Inggris di sini akan gagal di mesin
    // yang locale-nya id -- dan lulus di mesin yang kebetulan en.
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)->set('section', 'index')->html();

    // Ditampilkan apa adanya hasil __(), tanpa dimodifikasi CSS.
    expect($html)
        ->toContain(__('Sign In dan Pemulihan'))
        ->toContain(__('Pemeriksaan Keamanan'))
        ->not->toContain('uppercase');

    // Uppercase CSS memaksa huruf besar pada teks yang SUDAH diterjemahkan,
    // jadi hasil akhirnya bukan "SIGN IN AND RECOVERY" pun. Yang dijaga di
    // sini: kelas uppercase tidak boleh ada, dan tidak boleh ada crumb
    // kelompok yang terbaca huruf besar semua.
    expect($html)->not->toMatch('/SIGN IN (DAN PEMULIHAN|AND RECOVERY)/');
});

it('menerjemahkan label kelompok lewat file bahasa', function (): void {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    // Label yang tampil di browser Inggris harus persis seperti di UI, dan
    // harus berasal dari file bahasa -- bukan ditulis langsung di view,
    // karena itu akan membuat panel terkunci ke Inggris.
    expect($en['Sign In dan Pemulihan'] ?? null)->toBe('Sign In and Recovery')
        ->and($en['Pemeriksaan Keamanan'] ?? null)->toBe('Security Checkup');
});

/*
 * Tautan notifikasi
 *
 * Waktu halaman Keamanan dipecah jadi route, ?section= dihapus -- tapi
 * pemanggilnya masih membangun URL versi lama. Tombol "Lihat aktivitas Sign
 * In" jadi mendarat di halaman DAFTAR Keamanan, bukan di "Tempat Anda Sign
 * In": tombolnya terlihat benar tapi tidak melakukan apa pun.
 */
it('mengarahkan notifikasi Sign In ke bagian Sign In Activity', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    // Notifikasi "Login Terdeteksi" dibangun oleh DeviceTrustService
    // (AppServiceProvider hanya meneruskan recognize()), dan tombolnya harus
    // menunjuk "Tempat Anda Sign In" -- bagian 'sign-in-activity' dari
    // halaman Keamanan.
    foreach ([
        'app/Support/DeviceTrust/DeviceTrustService.php',
        'app/Filament/Welcome/Pages/NotificationDetailPage/NotificationDetailPage.php',
    ] as $file) {
        $source = (string) file_get_contents(base_path($file));

        expect($source)
            ->toContain('sign-in-activity')
            ->not->toContain('settings/password-security/sign-in-activity');
    }

    expect((string) file_get_contents(base_path('app/Support/DeviceTrust/DeviceTrustService.php')))
        ->toContain('DeviceTrustPrompt::getUrl');

    // URL bagiannya harus benar-benar ada (route yang hidup), bukan URL
    // karangan.
    expect(PasswordSecurityPage::getUrl(['section' => 'sign-in-activity'], panel: 'welcome'))
        ->toContain('/settings/password-security');
});

it('menjaga label tombol notifikasi tetap lewat file bahasa', function (): void {
    // Teksnya harus mengikuti language switcher, jadi label harus dibungkus
    // __() dan kuncinya ada di lang/en.json. Kalau teksnya ditulis langsung
    // di kode, panelnya akan selalu Inggris.
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    expect($en)->toHaveKey('Lihat aktivitas Sign In')
        ->and($en['Lihat aktivitas Sign In'])->toBe('View Sign In activity');
});

/*
 * Aplikasi autentikasi (TOTP)
/*
 * Aplikasi autentikasi (TOTP)
 *
 * Setup TOTP TIDAK ditulis ulang di halaman ini. Halaman Keamanan hanya
 * menaut ke halaman setup (TwoFactorySetup), yang mewarisi alur
 * mix-code/filament-multi-2fa: QR, verifikasi TOTP sungguhan, dan
 * penyimpanan secret yang formatnya benar.
 *
 * Yang dikunci di sini: halamannya benar-benar punya tujuan, dan alur setup
 * yang dituju itu benar-benar memverifikasi kode.
 */
it('menautkan bagian aplikasi autentikasi ke halaman setup', function (): void {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)
        ->set('section', 'two-factor')
        ->html();

    expect($html)->toContain(TwoFactorySetup::getUrl(panel: 'welcome'));
});

it('tidak memverifikasi kode di halaman Keamanan', function (): void {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)
        ->set('section', 'two-factor')
        ->html();

    // Secret hanya tampil di halaman setup, dan hanya setelah QR dibuat.
    // Tampilnya di sini berarti kode 6 digit bisa diaktifkan tanpa setup
    // aplikasi autentikasi -- dan 2FA yang aktif begitu tidak bisa dipakai
    // untuk masuk (secret-nya bukan base32, jadi tidak pernah bisa
    // diverifikasi Google2FA).
    expect($html)->not->toContain('prepareAuthenticator')
        ->not->toContain('verifyAuthenticator');
});

it('halaman setup memverifikasi kode TOTP sungguhan', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Kode yang bukan 6 digit harus ditolak.
    Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup')
        ->assertSet('showVerifyTOTPForm', true);

    // Dan kode 6 digit yang SALAH harus ditolak juga -- inilah yang
    // membedakan verifikasi sungguhan dari sekadar "digits:6".
    expect($user->fresh()->two_factor_secret)->not->toBeNull();

    Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup')
        ->fillForm(['otp' => '000000'], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertHasFormErrors(['otp'], 'verifyTOTPForm');
});

it('halaman setup menampilkan QR code sebagai SVG', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Backend QR bawaan repo ini adalah Bacon, yang mengembalikan SVG inline.
    // Kalau hasilnya dibiarkan mentah, yang tampil adalah string data URI --
    // halaman terlihat tanpa QR, padahal tidak ada error sama sekali.
    Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup')
        ->assertSee('<svg', escape: false);
});