<?php

/**
 * SignOut harus berlabel "Sign Out" (bukan "Log out"/"Keluar" dari terjemahan
 * per-bahasa) dan harus mendarat di Welcome Home dari ketiga panel.
 *
 * Label + ikon dikunci di RedirectsLogoutToWelcomeHome::signOutMenuItem(),
 * sedangkan tujuan redirect-nya di WelcomeLogoutResponse.
 */

use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Models\User\User;
use App\Providers\Filament\AdminPanelProvider\AdminPanelProvider;
use App\Providers\Filament\UserPanelProvider\UserPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(fn () => seed());

test('logout panel user -> welcome/home', function (): void {
    $user = User::factory()->create();

    actingAs($user, 'web')->post('/user/logout')->assertRedirect('/welcome/home');
});

test('logout panel admin -> welcome/home', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    actingAs($user, 'web')->post('/admin/logout')->assertRedirect('/welcome/home');
});

test('logout panel welcome -> welcome/home', function (): void {
    $user = User::factory()->create();

    actingAs($user, 'web')->post('/welcome/logout')->assertRedirect('/welcome/home');
});

test('item SignOut berlabel "Sign Out" di panel user dan admin', function (): void {
    // Konstanta trait tidak bisa diakses langsung (PHP >= 8.2), jadi lewat
    // kelas provider yang memakainya.
    expect(UserPanelProvider::SIGN_OUT_LABEL)->toBe('Sign Out')
        ->and(AdminPanelProvider::SIGN_OUT_LABEL)->toBe('Sign Out')
        ->and(UserPanelProvider::SIGN_OUT_ICON)->toBe(AdminPanelProvider::SIGN_OUT_ICON);

    foreach (['user', 'admin'] as $panelId) {
        $item = Filament::getPanel($panelId)->getUserMenuItems()['logout'] ?? null;

        expect($item)->not->toBeNull("panel {$panelId} tidak punya item logout");
        expect($item->getLabel())->toBe('Sign Out');
        expect($item->getIcon())->toBe(UserPanelProvider::SIGN_OUT_ICON);
    }
});

test('item SignOut tidak mengeset url, jadi tetap POST ke route logout panel', function (): void {
    // Kalau ->url() ikut diisi, form akan POST ke URL itu dan logout jadi
    // tidak benar-benar keluar dari panel yang sedang aktif.
    foreach (['user', 'admin'] as $panelId) {
        $item = Filament::getPanel($panelId)->getUserMenuItems()['logout'];

        expect($item->getUrl())->toBeNull();
    }
});

/*
 * Regresi 419 PAGE EXPIRED saat SignOut.
 *
 * Test HTTP di file ini TIDAK bisa menangkap bug itu: base VerifyCsrfToken
 * melakukan
 *
 *     $this->runningUnitTests() || $this->inExceptArray($request) || $this->tokensMatch($request)
 *
 * jadi di bawah PHPUnit, `runningUnitTests()` true dan pengecekan token
 * dilewati sepenuhnya. Karena itu test di atas hijau meski SignOut di browser
 * selalu 419. Dua test berikut memeriksa STRUKTUR middleware-nya langsung.
 */

test('setiap panel memakai VerifyCsrfToken subclass aplikasi, bukan kelas basis', function (): void {
    $base = \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class;

    foreach (['admin', 'user', 'welcome'] as $panelId) {
        $middleware = Filament::getPanel($panelId)->getMiddleware();

        // Pakai in_array, bukan toContain(): toContain() di Pest bersifat
        // variadic sehingga pesan Strings ikut dianggap elemen yang dicari.

        // CSRF tetap wajib ada di setiap panel.
        expect(in_array(VerifyCsrfToken::class, $middleware, true))->toBeTrue(
            "panel {$panelId} tidak memverifikasi CSRF sama sekali"
        );

        // Tapi harus yang punya $except. Yang base $except-nya kosong, sehingga
        // POST /{panel}/logout lolos lapisan global lalu ditolak lapisan panel
        // -> 419 PAGE EXPIRED.
        expect(in_array($base, $middleware, true))->toBeFalse(
            "panel {$panelId} memakai CSRF kelas basis, yang daftar pengecualiannya kosong"
        );
    }
});

test('pengecualian CSRF aplikasi menutup route logout tiap panel', function (): void {
    $csrf = app(VerifyCsrfToken::class);

    // $except bersifat protected, jadi dibaca lewat reflection.
    $except = (new ReflectionProperty($csrf, 'except'))->getValue($csrf);

    foreach (['admin', 'user', 'welcome'] as $panelId) {
        $logoutPath = Filament::getPanel($panelId)->getLogoutUrl();

        // Laravel mencocokkan $except terhadap decodedPath() -- TANPA slash
        // di depan. Karena itu 'admin/*' dan 'user/logout' tidak diawali '/'.
        $path = ltrim((string) parse_url($logoutPath, PHP_URL_PATH), '/');

        expect($path)->not->toBe('');

        expect(Str::is($except, $path))->toBeTrue(
            "route logout panel {$panelId} ({$path}) tidak dikecualikan dari CSRF"
        );
    }
});
