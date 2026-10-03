<?php

/**
 * Layout kartu ShortcutStats per platform.
 *
 * Dikendalikan di resources/views/Shared/widgets/shortcut-stats.blade.php:
 *
 *   - mobile (aplikasi shell Android/iOS + browser mobile): satu kartu per
 *     halaman yang digeser swipe (scroll-snap), titik penanda, dan animasi
 *     slide berurutan. Class: shortcut-stats-slide shortcut-stats-track.
 *   - tablet, macOS, desktop, desktop app: grid 4 kolom, tanpa kedua kelas.
 *
 * Widget diuji lewat Livewire (bukan HTTP) karena Filament memuatnya lewat
 * request terpisah, jadi HTML respons awal tidak memuat markup widget.
 */

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\User\Widgets\ShortcutStats\ShortcutStats as UserShortcutStats;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats as WelcomeShortcutStats;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

afterEach(function (): void {
    AppPlatform::reset();
    Filament::setCurrentPanel(null);
});

it('menjadi carousel satu kartu di mobile, dengan titik penanda', function (RuntimePlatform $platform): void {
    AppPlatform::fake($platform);

    // Panel welcome: tanpa login, jadi widget harus jalan sebagai tamu.
    Livewire::test(WelcomeShortcutStats::class)
        ->assertSee('shortcut-stats-slide')
        ->assertSee('shortcut-stats-track')
        // Swipe-nya scroll-snap, bukan JS: snap wajib ada di inline style.
        ->assertSee('scroll-snap-type: x mandatory')
        ->assertSee('overflow-x: auto')
        // Inline style WAJIB display:flex di mobile. Kalau masih grid, kartu
        // tetap 4 kolom -- flex-basis:100% diabaikan di grid, dan inilah
        // bug yang pernah terjadi (blade sudah benar, CSS belum terpakai).
        ->assertSee('display: flex')
        ->assertDontSee('grid-template-columns');
})->with([
    'app android' => RuntimePlatform::MobileAppAndroid,
    'app ios' => RuntimePlatform::MobileAppIos,
    'chrome android' => RuntimePlatform::WebsiteAndroid,
    'safari iphone' => RuntimePlatform::WebsiteIos,
]);

it('menjadi carousel satu kartu di panel user saat mobile', function (RuntimePlatform $platform): void {
    AppPlatform::fake($platform);

    // Panel user selalu punya user yang login (auth middleware), dan widget
    // User\ShortcutStats membaca $user->id tanpa null-check.
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UserShortcutStats::class)
        ->assertSee('shortcut-stats-track');
})->with([
    'app android' => RuntimePlatform::MobileAppAndroid,
    'app ios' => RuntimePlatform::MobileAppIos,
]);

it('tetap grid 4 kolom di tablet, macOS, desktop, dan desktop app', function (RuntimePlatform $platform): void {
    AppPlatform::fake($platform);

    Livewire::test(WelcomeShortcutStats::class)
        ->assertDontSee('shortcut-stats-slide')
        ->assertDontSee('shortcut-stats-track')
        ->assertDontSee('scroll-snap-type')
        // Grid 4 kolom tetap ditulis inline seperti sebelumnya.
        ->assertSee('grid-template-columns: repeat(4, minmax(0, 1fr))');
})->with([
    'desktop windows' => RuntimePlatform::WebsiteWindows,
    'macos' => RuntimePlatform::WebsiteMacOS,
    'desktop app windows' => RuntimePlatform::DesktopAppWindows,
    'desktop app macos' => RuntimePlatform::DesktopAppMacOS,
]);

it('tetap merender keempat kartu di semua platform', function (): void {
    $labels = ['Pesanan Saya', 'Favorit', 'Voucher Aktif', 'Keranjang'];

    foreach ([RuntimePlatform::MobileAppAndroid, RuntimePlatform::WebsiteWindows] as $platform) {
        AppPlatform::fake($platform);

        $html = Livewire::test(WelcomeShortcutStats::class)->html();

        foreach ($labels as $label) {
            expect($html)->toContain($label);
        }
    }
});

it('tidak error untuk tamu di panel welcome', function (): void {
    // Panel welcome bisa dibuka guest: seluruh lookup user di-null-kan dengan
    // aman, jadi widget harus tetap tampil dengan angka 0.
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    Livewire::test(WelcomeShortcutStats::class)
        ->assertOk()
        ->assertSee('shortcut-stats-track');
});

it('membuat titik penanda sejumlah kartu', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    // Satu titik per kartu; titik pertama aktif (snap = 0), sisanya pendek.
    $html = Livewire::test(WelcomeShortcutStats::class)->html();

    expect(substr_count($html, "snap === 0"))
        ->toBe(1)
        ->and(substr_count($html, "snap === 3"))
        ->toBe(1);
});

/*
 * ── Guard CSS ────────────────────────────────────────────────────────────
 *
 * Test di atas HANYA membuktikan markup Blade benar. Kegagalan yang benar-benar
 * terjadi di lapangan tidak terlihat di sini: blade sudah benar, tapi
 * `display: flex` di inline style kalah oleh `display: grid !important` yang
 * sudah ada di Shared.css -- sehingga container tetap grid, kartu tetap 4
 * kolom, dan flex-basis:100% diabaikan sepenuhnya.
 *
 * Jadi test ini memeriksa sumber CSS-nya secara langsung, karena tidak ada
 * cara asserts cascade dari PHP.
 */
it('menumbangkan display:grid !important yang sudah ada di Shared.css', function (): void {
    $css = file_get_contents(resource_path('css/Shared/Shared.css'));

    // 1. Aturan grid lama yang harus ditumbangkan.
    expect($css)
        ->toContain('display: grid !important')
        ->toContain('grid-template-columns: repeat(4, minmax(0, 1fr)) !important');

    // 2. Override carousel: WAJIB !important (inline style kalah dari
    //    !important di stylesheet) dan WAJIB memakai :has() supaya
    //    specificity-nya (0,3,0) melampaui aturan lama (0,2,0).
    expect($css)
        ->toMatch('/\.fi-wi-stats-overview-stats-ctn\.shortcut-stats-track:has\(\.home-stat-card\)[^{]*\{[^}]*display:\s*flex\s*!important/s')
        ->toMatch('/\.fi-wi-stats-overview-stats-ctn\.shortcut-stats-track:has\(\.home-stat-card\)[^{]*\{[^}]*grid-template-columns:\s*none\s*!important/s');

    // 3. Aturan anak kartunya: satu kartu per halaman.
    expect($css)
        ->toMatch('/\.shortcut-stats-track\s*>\s*\*\s*\{[^}]*flex:\s*0\s+0\s+100%/s')
        ->toMatch('/\.shortcut-stats-track\s*>\s*\*\s*\{[^}]*scroll-snap-align:\s*start/s');
});

it('bundle CSS yang ter-build sudah memuat override carousel', function (): void {
    $manifestPath = public_path('build/manifest.json');

    // Belum ada build sama sekali (fresh checkout): bukan kegagalan test ini.
    if (! is_file($manifestPath)) {
        expect(true)->toBeTrue();

        return;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $cssEntries = array_filter(
        array_keys($manifest),
        static fn (string $key): bool => str_ends_with($key, '.css')
    );

    expect($cssEntries)->not->toBeEmpty();

    foreach ($cssEntries as $entry) {
        $file = public_path('build/'.$manifest[$entry]['file']);

        expect(is_file($file))->toBeTrue("Bundle CSS hilang: {$entry}");

        $contents = (string) file_get_contents($file);

        // Manifest bisa basi -- bandulnya hanya berubah kalau ada build baru.
        // Kalau rule ini hilang dari bundle, Meaning-nya build-nya belum
        // dijalankan ulang dan browser masih menerima CSS lama.
        expect($contents)
            ->toContain('shortcut-stats-track:has(.home-stat-card)')
            ->and($contents)
            ->toMatch('/shortcut-stats-track:has\(\.home-stat-card\)[^{]*\{[^}]*display:flex!important/s');
    }
});
