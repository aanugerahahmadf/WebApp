<?php

use App\Models\User\User;
use App\Support\AppFont\AppFont;
use App\Support\ViewerFont\ViewerFont;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Pemilih huruf (font picker) untuk user, tamu, dan admin.
 *
 * Yang dijaga di sini:
 *
 *   1. SEMUA file font di assets/fonts/ terpasang (public/fonts/) dan
 *      tiap keluarga punya @font-face di public/css/app-font.css --
 *      "jangan 1 file": satu keluarga per berkas font.
 *   2. Pilihannya BUTTON (action) yang membuka modal grid, BUKAN
 *      dropdown select searchable.
 *   3. Berada di halaman Pengaturan, bukan di top navigation.
 *   4. Tamu boleh memilih (session) dan user boleh memilih
 *      (users.font_preference) -- dan pilihan itu yang dipakai CSS
 *      storefront (website + Dekstop App).
 *   5. Keluarga yang tidak dikenal DITOLAK (jatuh ke default), tidak
 *      ditulis mentah ke CSS.
 */

it('installs every font file from the local asset folders', function (): void {
    $src = base_path('assets/fonts');
    $dst = public_path('fonts');

    $sources = collect();
    foreach (glob($src.'/*', GLOB_ONLYDIR) ?: [] as $folder) {
        $name = basename($folder);

        foreach (glob($folder.'/*.{ttf,txt}', GLOB_BRACE) ?: [] as $file) {
            $sources->push($name.'/'.basename($file));
        }
        foreach (glob($folder.'/static/*.{ttf,txt}', GLOB_BRACE) ?: [] as $file) {
            $sources->push($name.'/static/'.basename($file));
        }
    }

    expect($sources)->not->toBeEmpty();

    $missing = $sources->reject(fn (string $rel): bool => is_file($dst.'/'.$rel))->all();

    expect($missing)->toBe([], 'File font belum terpasang di public/fonts/: '.implode(', ', array_slice($missing, 0, 10)));
});

it('gives every catalog family a font-face in the stylesheet', function (): void {
    $css = (string) file_get_contents(public_path('css/app-font.css'));

    $families = AppFont::families();

    expect($families)->toContain(AppFont::DEFAULT_FAMILY);
    expect(count($families))->toBeGreaterThan(100);

    foreach ($families as $family) {
        expect($css)->toContain("font-family: '{$family}';");
    }
});

it('lists every family as a clickable card, not a select', function (): void {
    $html = $this->get('/settings')->getContent();

    // Tombol aksi (bukan <select>).
    expect($html)->toContain('fi-font-picker');
    expect($html)->toContain('mountAction');

    // Grid di dalam modal: setiap keluarga punya kartunya sendiri, dan
    // kartu itu MEMAKAI hurufnya (inline font-family), jadi pilihannya
    // terlihat bentuknya -- bukan cuma dibaca.
    $component = \Livewire\Livewire::test(\App\Livewire\Shared\FontPicker\FontPicker::class, ['target' => 'viewer']);
    $component->mountAction('pickFont');

    $component->assertSee('fi-font-picker-card');
    $component->assertSee('Roboto');
    $component->assertSee('Asap Sharp');
    $component->assertSee("font-family: 'Roboto' !important;", false);

    // Tiap keluarga di katalog punya kartunya -- tidak ada yang dipotong
    // ("jangan 1 file": satu keluarga per berkas font yang terpasang).
    $cards = substr_count($component->html(), 'fi-font-picker-card');

    expect($cards)->toBe(count(AppFont::families()));
});

it('does not put the font picker in the top navigation', function (): void {
    $html = $this->get('/home')->getContent();

    // Pemilih huruf hanya di halaman Pengaturan.
    expect($html)->not->toContain('fi-font-picker-card');
    expect($html)->not->toContain('mountAction(\'pickFont\')');
});

it('lets a guest pick a font and applies it to the storefront css', function (): void {
    $this->get('/settings')->assertStatus(200);

    $this->withSession([ViewerFont::SESSION_KEY => 'Roboto'])
        ->get('/home')
        ->assertStatus(200)
        ->assertSee("--app-font-family: 'Roboto'", false);
});

it('stores the picked font on the user account and applies it to the css', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings')->assertStatus(200);

    expect(ViewerFont::set('Open Sans'))->toBeTrue();

    $user->refresh();
    expect($user->font_preference)->toBe('Open Sans');

    $this->actingAs($user)
        ->get('/home')
        ->assertStatus(200)
        ->assertSee("--app-font-family: 'Open Sans'", false);
});

it('rejects an unknown family instead of writing it into the css', function (): void {
    expect(ViewerFont::set('Font Palsu 123'))->toBeFalse();
    expect(ViewerFont::set(''))->toBeFalse();

    expect(ViewerFont::current())->toBe(AppFont::family());
});

it('lets the admin pick the storefront and the admin panel font separately', function (): void {
    \Illuminate\Support\Facades\Cache::clear();
    \Pest\Laravel\seed();
    $admin = \App\Models\Admin\Admin::factory()->create();
    $admin->assignRole('super_admin');

    // Halaman Tipografi ada di panel admin dan memuat DUA pemilih huruf.
    $response = $this->actingAs($admin, 'admin')->get('/admin/tipografi');
    $response->assertStatus(200);

    $html = $response->getContent();

    // Penanda target (bukan label tombol): label ikut bahasa, penanda tidak.
    expect($html)->toContain('data-font-picker-target="storefront_default"');
    expect($html)->toContain('data-font-picker-target="admin"');

    // Halamannya terdaftar di group "System Settings" (id: "Pengaturan
    // Sistem") -- bukan group liar yang tidak pernah dikontrol siapa pun.
    expect(\App\Filament\Admin\Pages\FontSettingsPage\FontSettingsPage::getNavigationGroup())
        ->toBe(__('Pengaturan Sistem'));

    // Admin memilih huruf panel admin -> tersimpan di appearance.admin_family,
    // dan CSS panel admin ikut memakainya.
    $component = \Livewire\Livewire::test(\App\Livewire\Shared\FontPicker\FontPicker::class, ['target' => 'admin']);
    $component->call('applyFont', 'Roboto');

    expect(\App\Models\FmAppSetting\FmAppSetting::appearance()['admin_family'])->toBe('Roboto');

    \App\Support\AppFont\AppFont::forgetCache();

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    if ($response->isRedirect()) {
        $response = $this->actingAs($admin, 'admin')->get($response->headers->get('Location'));
    }

    $response->assertStatus(200);
    $response->assertSee("--app-font-family: 'Roboto'", false);

    // Admin juga bisa mengubah BAWAAN huruf website (tetap terpisah dari
    // huruf panel adminnya).
    $storefront = \Livewire\Livewire::test(\App\Livewire\Shared\FontPicker\FontPicker::class, ['target' => 'storefront_default']);
    $storefront->call('applyFont', 'Open Sans');

    expect(\App\Models\FmAppSetting\FmAppSetting::appearance()['family'])->toBe('Open Sans');
    expect(\App\Models\FmAppSetting\FmAppSetting::appearance()['admin_family'])->toBe('Roboto');
});
