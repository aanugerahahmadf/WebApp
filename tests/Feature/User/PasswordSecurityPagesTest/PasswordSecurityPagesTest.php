<?php

/**
 * Halaman "Kata Sandi dan Keamanan" yang sudah dipecah per route.
 *
 * Dulunya semuanya satu kelas 469 baris dengan query ?section=, yang
 * menggabungkan 7 halaman jadi satu file dan mustahil dicari asal-usulnya.
 * Sekarang tiap bagian punya kelas, folder, view, dan route sendiri.
 *
 * Test ini mengunci dua hal:
 *
 *   1. Register -- kedelapan halaman benar-benar terdaftar di panel User,
 *      tidak ada yang tertinggal jadi 404.
 *
 *   2. Render -- setiap halaman benar-benar bisa dirender. Ini yang paling
 *      rawan: view yang salah path akan lolos saat kelasnya syntax-nya
 *      bersih, tapi meledak jadi 500 saat dibuka.
 */

use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth as AuthTwoFactorAuth;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\Checkup\Checkup;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\RecentEmails\RecentEmails;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\BrowserSessionsComponent\BrowserSessionsComponent;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\SignInActivity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\ChangePassword\ChangePassword;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\SavedLogin\SavedLogin;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Models\User\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// getUrl() milik Filament butuh panel aktif.
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

/**
 * [class, url]
 *
 * Dipakai sebagai dataset, jadi tiap elemen harus berupa array berisi dua
 * nilai -- bukan peta asosiatif. Peta asosiatif akan mengirim satu argumen
 * saja, sehingga testnya diam-dim menguji URL sebagai nama class.
 */
dataset('halaman keamanan', [
    [PasswordSecurityPage::class, 'user/settings/password-security'],
    [ChangePassword::class, 'user/settings/password-security/change-password'],
    [TwoFactor::class, 'user/settings/password-security/two-factor'],
    [TwoFactorySetup::class, 'user/settings/password-security/two-factor/setup'],
    [SavedLogin::class, 'user/settings/password-security/saved-login'],
    [SignInActivity::class, 'user/settings/password-security/sign-in-activity'],
    [RecentEmails::class, 'user/settings/password-security/recent-emails'],
    [Checkup::class, 'user/settings/password-security/checkup'],
]);

/*
 * Render -- yang paling rawan. View yang salah path lolos saat kelasnya
 * syntax-nya bersih, tapi meledak jadi 500 begitu halamannya dibuka.
 */
it('merender setiap halaman keamanan tanpa error', function (string $class) {
    $this->actingAs(User::factory()->create());

    Livewire::test($class)->assertSuccessful();
})->with('halaman keamanan');

it('membuka setiap halaman keamanan di URL yang semestinya', function (string $class, string $path) {
    $this->actingAs(User::factory()->create());

    $this->get($path)->assertSuccessful();
})->with('halaman keamanan');

/*
 * Pemecahan yang harus tetap begitu
 */
it('tidak lagi memakai query ?section= untuk berpindah antar halaman', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->uri(), 'user/settings/password-security'));

    expect($routes)->toHaveCount(8);

    // Setiap halaman punya URI sendiri; tidak ada satu URI dengan ?section=
    // yang menggabungkan ketujuhnya.
    foreach ($routes as $route) {
        expect($route->uri())->not->toContain('section=');
    }
});

it('menaruh setiap kelas keamanan di foldernya sendiri', function () {
    $root = app_path('Filament/User/Pages/SettingsPage/PasswordSecurityPage');

    // [class, lokasi relatif dari root]. BrowserSessionsComponent bukan
    // turunan langsung dari root: ia milik halaman SignInActivity, jadi
    // diletakkan di sebelahnya.
    $classes = [
        [ChangePassword::class, 'SignInAndRecovery/ChangePassword/ChangePassword.php'],
        [TwoFactor::class, 'SignInAndRecovery/TwoFactor/TwoFactor.php'],
        [TwoFactorySetup::class, 'SignInAndRecovery/TwoFactorySetup/TwoFactorySetup.php'],
        [SavedLogin::class, 'SignInAndRecovery/SavedLogin/SavedLogin.php'],
        [SignInActivity::class, 'SecurityCheck/SignInActivity/SignInActivity.php'],
        [RecentEmails::class, 'SecurityCheck/RecentEmails/RecentEmails.php'],
        [Checkup::class, 'SecurityCheck/Checkup/Checkup.php'],
        [BrowserSessionsComponent::class, 'SecurityCheck/SignInActivity/BrowserSessionsComponent/BrowserSessionsComponent.php'],
        [PasswordSecurityPage::class, 'PasswordSecurityPage.php'],
    ];

    foreach ($classes as [$class, $rel]) {
        // Konvensi repo: 1 file = 1 folder, jadi file berada di folder
        // bernama sama dengan class-nya.
        expect(class_exists($class), "{$class} tidak bisa di-autoload")->toBeTrue();
        expect(is_file($root.'/'.$rel), "{$rel} tidak ada")->toBeTrue();
    }
});

it('membuang blade lama yang menyatukan semua bagian', function () {
    $old = resource_path('views/User/pages/settings-page/password-security-page/password-security-page.blade.php');

    expect(is_file($old))->toBeFalse();
});

it('membuang kelas lama yang menumpuk semua bagian', function () {
    $old = app_path('Filament/User/Pages/SettingsPage/PasswordSecurityPage.php');

    expect(is_file($old))->toBeFalse();
});

/*
 * Breadcrumb
 *
 * Naik halaman dulu diwujudkan tombol "Kembali". Sekarang breadcrumb, dan
 * crumb itu memuat nama kelompok -- sebelumnya semua halaman detail punya
 * induk yang identik, sehingga "Change Password" dan "Saved Sign In" tidak
 * bisa dibedakan dari posisinya.
 */
it('menampilkan kelompok di breadcrumb halaman detail', function (string $class, string $group) {
    $this->actingAs(User::factory()->create());

    expect(Livewire::test($class)->html())->toContain($group);
})->with([
    [ChangePassword::class, 'Sign In dan Pemulihan'],
    [TwoFactor::class, 'Sign In dan Pemulihan'],
    [TwoFactorySetup::class, 'Sign In dan Pemulihan'],
    [SavedLogin::class, 'Sign In dan Pemulihan'],
    [SignInActivity::class, 'Pemeriksaan Keamanan'],
    [RecentEmails::class, 'Pemeriksaan Keamanan'],
    [Checkup::class, 'Pemeriksaan Keamanan'],
]);

it('menyambung breadcrumb dari Pengaturan sampai halaman ini', function (string $class) {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test($class)->html();

    expect($html)
        ->toContain('Pengaturan')
        ->toContain('Kata Sandi dan Keamanan');
})->with('halaman keamanan');

it('tidak lagi memakai tombol kembali', function (string $class) {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test($class)->html();

    // Naik lewat crumb, bukan tombol.
    expect($html)
        ->not->toContain(__('Kembali ke Pengaturan'))
        ->not->toContain(__('Kembali ke Kata Sandi dan Keamanan'))
        ->not->toContain('heroicon-m-arrow-left');
})->with('halaman keamanan');

it('membuat crumb tebal dan terbaca di light maupun dark', function () {
    // Abu-abu (dark:text-gray-400) menghilang di mode gelap: dasar panel
    // gelap hampir sama nadanya, sehingga teksnya praktis tak terbaca.
    // Karena itu crumb butuh pasangan kelas terang-gelap, bukan satu warna
    // abu-abu untuk dua mode.
    $view = (string) file_get_contents(
        resource_path('views/User/vendor/filament/components/breadcrumbs.blade.php')
    );

    expect($view)
        ->toContain('font-bold')
        ->toContain('text-gray-950')
        ->toContain('dark:text-white')
        ->not->toContain('fi-breadcrumbs-item-label text-sm font-medium text-gray-500');
});

it('tidak menggandakan "Kata Sandi dan Keamanan" di crumb index', function () {
    // Induk index adalah kata yang sama dengan judulnya, jadi tanpa penjaga
    // crumbsnya jadi: Pengaturan / Kata Sandi dan Keamanan / Kata Sandi dan
    // Keamanan.
    $this->actingAs(User::factory()->create());

    $html = Livewire::test(PasswordSecurityPage::class)->html();

    expect(substr_count($html, 'Kata Sandi dan Keamanan'))->toBeLessThan(4);
});

it('tidak menulis label kelompok dengan huruf kapital', function () {
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

    $html = Livewire::test(PasswordSecurityPage::class)->html();

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

it('menerjemahkan label kelompok lewat file bahasa', function () {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    // Label yang tampil di browser Inggris harus persis seperti di UI, dan
    // harus berasal dari file bahasa -- bukan ditulis langsung di view,
    // karena itu akan membuat panel User terkunci ke Inggris.
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
it('mengarahkan notifikasi Sign In ke halaman Sign In Activity, bukan ke ?section=', function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $expected = SignInActivity::getUrl(panel: 'user');

    // Dipakai AppServiceProvider saat mencatat "Login Detected", dan
    // NotificationDetailPage untuk tombolnya.
    foreach ([
        'app/Providers/AppServiceProvider/AppServiceProvider.php',
        'app/Filament/User/Pages/NotificationDetailPage/NotificationDetailPage.php',
    ] as $file) {
        $source = (string) file_get_contents(base_path($file));

        expect($source)
            ->toContain('SignInActivity::getUrl')
            ->not->toContain("'section' => 'sign-in-activity'")
            ->not->toContain("getUrl(['section'");
    }

    expect($expected)->toContain('/user/settings/password-security/sign-in-activity');
});

it('menjaga label tombol notifikasi tetap lewat file bahasa', function () {
    // Teksnya harus mengikuti language switcher, jadi label harus dibungkus
    // __() dan kuncinya ada di lang/en.json. Kalau teksnya ditulis langsung
    // di kode, panel User dan Welcome akan selalu Inggris.
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    expect($en)->toHaveKey('Lihat aktivitas Sign In')
        ->and($en['Lihat aktivitas Sign In'])->toBe('View Sign In activity');
});

/*
 * Halaman setup 2FA
 *
 * Paket mix-code/filament-multi-2fa memakai layout.simple, yang tidak punya
 * Top Navigation maupun breadcrumb -- tampilannya polos, hanya tombol bahasa
 * di kanan atas. Karena itu halaman ini mengembalikan layout ke default
 * Filament, dan view-nya memakai <x-filament-panels::page> bukan page.simple.
 */
it('memakai halaman setup milik paket, bukan salinan sendiri', function () {
    // Alur tiga langkah (pilih tipe -> verifikasi -> simpan) milik paket.
    // Disalin ke sini berarti setiap perbaikan verifikasi TOTP harus
    // diterapkan dua kali, dan pasti ketinggal satu.
    expect(is_subclass_of(
        TwoFactorySetup::class,
        MixCode\FilamentMulti2fa\Pages\TwoFactorySetup::class
    ))->toBeTrue();
});

it('menampilkan Top Navigation di halaman setup 2FA', function () {
    $this->actingAs(User::factory()->create());

    // Lewat HTTP, bukan Livewire::test(): yang terakhir hanya merender
    // komponennya, tanpa layout halaman -- jadi topbar, breadcrumb, dan
    // judul tidak akan pernah muncul di sana meski halamannya benar.
    $html = $this->get(TwoFactorySetup::getUrl(panel: 'user'))->assertSuccessful()->getContent();

    // fi-topbar = Top Navigation panel. Kalau hilang, berarti halaman ini
    // masih memakai layout.simple dari paket.
    expect($html)->toContain('fi-topbar');

    // Breadcrumb pun harus ikut tampil -- itu tujuan dikembalikan
    // layout.simple ke layout.index.
    expect($html)->toContain(__('Sign In dan Pemulihan'));
});

it('tidak memakai layout polos di halaman setup 2FA', function () {
    $page = app(TwoFactorySetup::class);

    // layout.simple = tanpa topbar, tanpa breadcrumb, tanpa judul halaman.
    expect($page->getLayout())->not->toContain('layout.simple')
        ->and($page->getLayout())->toContain('layout.index');
});

it('kembali ke halaman Two Factor setelah setup selesai', function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    // Paket mengembalikan ke redirectAfterVerifyUrl() miliknya, yang menulis
    // ke URL panel. Itu benar untuk halaman login, tapi salah di sini:
    // pengguna sedang mengatur keamanan dari Pengaturan.
    expect(TwoFactor::getUrl(panel: 'user'))->toContain('/user/settings/password-security/two-factor');
});

it('menampilkan kode manual pada langkah verifikasi TOTP', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Secret baru dibuat setelah tombol Setup ditekan -- bukan saat halaman
    // dibuka. Field yang pakai default() akan tampil kosong di sini, karena
    // nilainya sudah-dihitung sebelum secret ada.
    $secret = $user->fresh()->two_factor_secret;

    Livewire::test(TwoFactorySetup::class)
        ->set('data.two_factor_type', MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Totp->value)
        ->call('setup')
        ->assertSet('showVerifyTOTPForm', true)
        ->assertSee($secret);
});

it('menampilkan QR code sebagai SVG, bukan teks data URI', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Backend QR bawaan repo ini adalah Bacon, yang mengembalikan SVG inline.
    // Kalau hasilnya dibiarkan mentah, yang tampil adalah string data URI --
    // halaman terlihat tanpa QR, padahal tidak ada error sama sekali.
    Livewire::test(TwoFactorySetup::class)
        ->set('data.two_factor_type', MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Totp->value)
        ->call('setup')
        ->assertSee('<svg', escape: false);
});

it('tidak menampilkan kode manual sebagai kotak yang bisa diedit', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Kode manual lewat infolist (fi-in-text), bukan kotak input. Dulu ia
    // TextInput readOnly, dan nilainya tetap kosong karena state form sudah
    // terisi sebelum secret ada -- lihat HandlesTwoFactorAuthenticator.
    //
    // Yang dicek bukan "tidak ada input sama sekali": field OTP memang input,
    // itu memang harus bisa diketik.
    // Secret dibaca SEBELUM setup() dipanggil: method itu yang membuat secret
    // baru, jadi membacanya sesudahnya bisa mendapat nilai yang berbeda dari
    // yang dirender.
    $secret = $user->fresh()->two_factor_secret;

    $html = Livewire::test(TwoFactorySetup::class)
        ->set('data.two_factor_type', MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Totp->value)
        ->call('setup')
        ->html();

    expect($html)->toContain('fi-in-text')->toContain($secret);
});

it('menampilkan tampilan yang sama di halaman Auth dan di Pengaturan', function () {
    // Keduanya harus lewat trait yang sama. Kalau salah satu menulis QR-nya
    // sendiri, keduanya akan terlihat berbeda tanpa ada yang menyadarikan.
    $trait = 'App\\Filament\\Shared\\Concerns\\HandlesTwoFactorAuthenticator\\HandlesTwoFactorAuthenticator';

    // class_uses_recursive, bukan class_uses: trait-nya dipakai oleh kelas
    // dasar TwoFactorAuthenticatorPage, bukan langsung oleh kedua halaman.
    expect(class_uses_recursive(AuthTwoFactorAuth::class))->toContain($trait)
        ->and(class_uses_recursive(TwoFactorySetup::class))->toContain($trait);

    // Dan keduanya memakai layout yang memuat Top Navigation.
    expect(app(AuthTwoFactorAuth::class)->getLayout())->toContain('layout.index')
        ->and(app(TwoFactorySetup::class)->getLayout())->toContain('layout.index');
});

it('merender halaman Auth 2FA dengan QR, Kode Manual, dan Top Navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $secret = $user->fresh()->two_factor_secret;

    // Halaman ini memakai alur paket: QR belum ada sebelum tipe dipilih,
    // jadi test harus menjalankan langkahnya -- bukan hanya memuat halaman.
    // Menilai QR saat halaman pertama dibuka akan selalu gagal.
    $html = Livewire::test(AuthTwoFactorAuth::class)
        ->set('data.two_factor_type', MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Totp->value)
        ->call('setup')
        ->assertSet('showVerifyTOTPForm', true)
        ->html();

    expect($html)
        ->toContain('fi-in-text')
        ->toContain('<svg')
        ->toContain(__('Kode Manual'))
        ->toContain($secret);
});

it('menampilkan Top Navigation di halaman Auth 2FA', function () {
    $this->actingAs(User::factory()->create());

    // Lewat HTTP: Livewire::test() hanya merender komponennya, tanpa layout
    // halaman, jadi topbar tidak akan pernah muncul di sana.
    $this->get('/user/two-factor-auth')->assertSuccessful()->assertSee('fi-topbar');
});

it('menampilkan pemilih tipe 2FA di kedua halaman', function () {
    $this->actingAs(User::factory()->create());

    // Keduanya mewarisi alur paket, jadi Email / Authenticator App / None
    // harus tersedia di kedua halaman -- termasuk di halaman Auth, supaya
    // pengguna sendiri yang memutuskan memakai atau tidak memakai 2FA.
    foreach (['/user/two-factor-auth', '/user/settings/password-security/two-factor/setup'] as $path) {
        $html = $this->get($path)->assertSuccessful()->getContent();

        expect($html)
            ->toContain(\MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Totp->value)
            ->toContain(\MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::Email->value)
            ->toContain(\MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value);
    }
});