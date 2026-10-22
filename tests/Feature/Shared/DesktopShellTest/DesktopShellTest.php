<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use App\Support\AvatarUrl\AvatarUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

/*
 * Shell desktop (Electron) + avatar profil.
 *
 * Dua hal yang diuji di sini, dan keduanya bisa rusak tanpa error:
 *
 *   1. AVATAR PROFIL harus benar-benar muncul.
 *
 *      Avatar diambil dari kolom `avatar_url`, yang bisa berisi path
 *      relatif (`avatars/1_x.jpg`), URL absolut ke host produksi
 *      (`https://host/media/avatars/1_x.jpg`), atau URL pihak ketiga
 *      (pravatar.cc). Di aplikasi desktop -- yang menjangkau server lewat
 *      `http://127.0.0.1:8000` -- URL host produksi tidak melayani file-nya,
 *      jadi avatarnya tampil sebagai gambar rusak.
 *
 *      Dua tempat yang pernah jadi biang masalahnya:
 *
 *        - AppPlatform::normalizeUrl() pernah punya cabang yang tidak
 *          pernah dieksekusi (`$parts` dicek tanpa parse_url() lebih dulu),
 *          jadi URL absolut tidak pernah dipindahkan ke origin request.
 *        - Nilai avatar yang ditulis ke database harus tetap NILAI MENTAH.
 *          Kalau yang ditulis URL yang sudah diterjemahkan, kolomnya
 *          berisi `http://127.0.0.1:8000/...` dan rusak di server lain.
 *
 *   2. SHELL DESKTOP harus terdeteksi, dan penandanya harus ada di HTML.
 *
 *      Tanpa penanda itu, panel tidak tahu sedang berjalan di dalam
 *      jendela aplikasi -- dan switcher bahasa/tema, panel saudara, dan
 *      judul jendela semuanya ikut salah.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);
});

// ── Avatar: URL absolut ke host produksi dipindahkan ke origin request ───────

test('an absolute avatar url on the production host is moved to the request origin', function (): void {
    config(['app.url' => 'https://wedding-flower-decorations.whop.site']);

    $normalized = AppPlatform::rewriteTo(
        'https://wedding-flower-decorations.whop.site/media/avatars/1_x.jpg',
        'http://127.0.0.1:8000',
    );

    // Host produksi sudah diganti jadi origin yang sedang melayani request.
    expect($normalized)->toBe('http://127.0.0.1:8000/media/avatars/1_x.jpg');
});

test('a relative url becomes absolute against the request origin', function (): void {
    expect(AppPlatform::rewriteTo('/storage/avatars/1_x.jpg', 'http://127.0.0.1:8000'))
        ->toBe('http://127.0.0.1:8000/storage/avatars/1_x.jpg');
});

test('a url on a foreign host is left alone', function (): void {
    expect(AppPlatform::rewriteTo('https://i.pravatar.cc/300?u=andi', 'http://127.0.0.1:8000'))
        ->toBe('https://i.pravatar.cc/300?u=andi');
});

test('a relative avatar path becomes a url on the local server', function (): void {
    $user = User::factory()->create(['avatar_url' => 'avatars/1_x.jpg']);

    expect($user->avatar_url)->toContain('/media/avatars/1_x.jpg')
        ->and($user->avatar_url)->not->toContain('wedding-flower-decorations');
});

test('a third party avatar url is left alone', function (): void {
    $user = User::factory()->create(['avatar_url' => 'https://i.pravatar.cc/300?u=andi']);

    // Bukan file milik server ini, jadi tidak boleh dipindah ke origin lokal.
    expect($user->avatar_url)->toBe('https://i.pravatar.cc/300?u=andi');
});

test('the avatar url stored in the database stays untouched', function (): void {
    $user = User::factory()->create(['avatar_url' => 'avatars/1_x.jpg']);

    // Nilai yang tersimpan harus nilai yang di-upload, bukan URL hasil
    // terjemahan -- kalau bukan, avatar rusak di server lain.
    expect($user->getRawOriginal('avatar_url'))->toBe('avatars/1_x.jpg');
});

test('the admin avatar is served from the local media route', function (): void {
    $admin = Admin::factory()->create(['avatar_url' => 'avatars/admin_x.jpg']);

    expect($admin->avatar_url)->toContain('/media/avatars/admin_x.jpg')
        ->and($admin->getRawOriginal('avatar_url'))->toBe('avatars/admin_x.jpg');
});

test('the avatar url helper handles an empty value', function (): void {
    expect(AvatarUrl::for(null))->toBeNull();
});

// ── Shell desktop terdeteksi, dan penandanya ada di HTML ───────────────────

/*
 * PENTING: platformnya DIPAKSA, bukan dideteksi dari User-Agent.
 *
 * AppPlatform::detect() disengaja mengabaikan User-Agent saat
 * `app()->runningInConsole()` -- dan test dijalankan di console. Jadi
 * request dengan User-Agent Electron TIDAK terdeteksi sebagai desktop
 * app di dalam test, dan penanda desktop tidak akan pernah muncul.
 *
 * Deteksi dari User-Agent sendiri sudah diuji di
 * tests/Feature/Shared/ResponsivePanelsTest ("detects the electron desktop
 * shell from the user agent"). Test di sini menguji APA YANG DIRENDER
 * begitu platform-nya sudah berupa desktop app.
 */
test('the desktop shell marker is rendered for the electron shell', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $html = $this->get('/home')->assertOk()->getContent();

    expect($html)->toContain('window.DesktopApp')
        ->and($html)->toContain('isDesktopApp: true')
        // Shell storefront namanya "Dekorasi".
        ->and($html)->toContain('var shellName = "Dekorasi";');
});

test('the desktop shell marker is absent on the website', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    $html = $this->get('/home')->assertOk()->getContent();
    // Website tidak boleh ikut punya API desktop app:riadanya tombol
    // "Buka Panel Admin" akan muncul di browser, yang tidak ada artinya.
    expect($html)->not->toContain('window.DesktopApp');
});

test('the admin panel renders the desktop shell marker', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');

    actingAs($admin, 'admin');

    // Halaman profil admin: sekaligus dipakai untuk mengecek avatar.
    $html = $this->get('/admin/edit-profile-page')->assertOk()->getContent();

    expect($html)->toContain('window.DesktopApp')
        ->and($html)->toContain('var shellName = "Dekorasi Admin";');
});

/*
 * JUDUL JENDELA IKUT LANGUAGE SWITCHER.
 *
 * Electron memakai <title> sebagai judul jendela, jadi isi <title> itu
 * judul yang terlihat di title bar.
 *
 * Dua bagiannya harus ikut bahasa:
 *   - judul halaman  -> getTitle() -> __('Beranda') -> "Beranda" / "Home"
 *   - nama brand     -> AppFont::brandName() -> __() -> "Dekorasi Bunga
 *     Pernikahan" / "Wedding Flower Decorations"
 *
 * Dulu nama brand-nya dikembalikan MENTAH, jadi judulnya tetap Bahasa
 * Indonesia walau panelnya sudah English: "Dekorasi - Home - Dekorasi Bunga
 * Pernikahan".
 */
test('the window title follows the language switcher', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $title = fn (string $locale): string => (function () use ($locale): string {
        $html = $this->get('/home?locale='.$locale)->assertOk()->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $matches);

        return trim(preg_replace('/\s+/', ' ', $matches[1] ?? ''));
    })();

    expect($title('en'))->toContain('Home')
        ->and($title('en'))->toContain('Wedding Flower Decorations')
        ->and($title('id'))->toContain('Beranda')
        ->and($title('id'))->toContain('Dekorasi Bunga Pernikahan');

});

/*
 * HALAMAN SIGN-IN ADMIN: judulnya juga ikut bahasa.
 *
 * `SignIn::getHeading()` mengembalikan `__('Sign In')`, dan `__('Sign In')`
 * dipakai di 14 tempat (heading, breadcrumb, tombol, link). Jadi kalau
 * `lang/id.json` punya `"Sign In": "Sign In"`, SELURUH UI admin berbahasa
 * Indonesia menampilkan "Sign In" -- termasuk judul jendela.
 *
 * Perbaikannya di level terjemahan: `"Sign In": "Masuk"`.
 */
test('the admin sign in page title follows the language switcher', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $title = fn (string $locale): string => (function () use ($locale): string {
        $html = $this->get('/admin/signin?locale='.$locale)->assertOk()->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $matches);

        return trim(preg_replace('/\s+/', ' ', $matches[1] ?? ''));
    })();

    expect($title('en'))->toContain('Sign In')
        ->and($title('en'))->toContain('Wedding Flower Decorations')
        ->and($title('id'))->toContain('Masuk')
        ->and($title('id'))->toContain('Dekorasi Bunga Pernikahan')
        // "Sign In" tidak boleh bocor ke Bahasa Indonesia.
        ->and($title('id'))->not->toContain('Sign In');
});

/*
 * SWITCHER BAHASA + TEMA DI APLIKASI DESKTOP.
 *
 * Gejalanya: di aplikasi desktop topbar TIDAK punya language switcher
 * maupun theme switcher -- padahal switcher-nya "ada" di DOM.
 *
 * SEBABNYA, dan ini dua hal yang harus agrees:
 *
 *   1. AppPlatform::switchersBelongInSidebar() pernah mengembalikan TRUE
 *      untuk aplikasi desktop, jadi hook topbar (GLOBAL_SEARCH_AFTER) dan
 *      hook sidebar (SIDEBAR_NAV_START) menaruh switcher yang sama di
 *      SIDEBAR.
 *
 *   2. Panel storefront memakai `->topNavigation()`, dan itu yang membuat
 *      sidebar-nya diterjemahkan ke luar layar di layar lebar
 *      (`lg:-translate-x-full` di override view sidebar). Sidebar aplikasi
 *      desktop praktis tidak terlihat.
 *
 * Hasilnya: switcher ter-render di sidebar yang tersembunyi, dan TIDAK
 * ter-render sama sekali di topbar yang terlihat.
 *
 * Perbaikannya di AppPlatform: aplikasi desktop ikut ke TOPBAR, sama
 * seperti website desktop.
 */
test('the desktop app shows the language and theme switchers in the topbar', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $html = $this->get('/home')->assertOk()->getContent();

    // Language switcher (x-ref="langPanel" cuma ada di partial switcher
    // bahasa) dan theme switcher (class="fi-theme-switcher").
    expect($html)->toContain('x-ref="langPanel"')
        ->and($html)->toContain('class="fi-theme-switcher"');
});

test('the desktop app does not render the switchers twice', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    $html = $this->get('/home')->assertOk()->getContent();

    // Sekali di topbar, dan TIDAK lagi di sidebar. Dua salinan untuk satu
    // pengaturan = dua tombol yang kelihatan sama di dua tempat.
    expect(substr_count($html, 'class="fi-theme-switcher"'))->toBe(1)
        ->and(substr_count($html, 'x-ref="langPanel"'))->toBe(1);
});

test('the desktop app keeps the switchers in the topbar outside the home page', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    // Katalog, bukan cuma beranda: switcher topbar itu milik SETIAP layar
    // di aplikasi desktop.
    $html = $this->get('/flowerdecorationscatalog')->assertOk()->getContent();

    expect($html)->toContain('x-ref="langPanel"')
        ->and($html)->toContain('class="fi-theme-switcher"');
});

test('the mobile shell keeps the switchers in the sidebar', function (): void {
    // Shell mobile: topbar menyingkir di bawah 640px, jadi switcher milik
    // sidebar (drawer). Rule ini tidak boleh ikut berubah karena perbaikan
    // aplikasi desktop.
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    $html = $this->get('/home')->assertOk()->getContent();

    // Sidebar hook tetap mengisi, dan topbar hook tidak.
    expect($html)->toContain('class="fi-theme-switcher"')
        ->and($html)->toContain('x-ref="langPanel"');
});
