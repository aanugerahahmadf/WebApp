<?php

use App\Models\Admin\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

/**
 * Enam halaman grup "Pengaturan Sistem" harus benar-benar terbuka.
 *
 * Test ini bukan test tampilan: tujuannya menangkap dua hal yang dulu terjadi
 * diam-diam --
 *
 *   1. Halaman 500 karena class/route hilang (tidak ada error di navigasi,
 *      cuma halaman kosong saat diklik), dan
 *   2. judul "IOS" (huruf besar semua) akibat `Str::ucwords()` milik Filament
 *      pada label yang sudah benar ("iOS").
 *
 * Halaman turunannya ikut diuji: form "Buat" Studio Basis Data (yang membuat
 * BERKAS, bukan baris tabel) dan Terminal Artisan yang ditautkan dari sana.
 *
 * Label sidebar sendiri diuji terpisah di SidebarLabelWrapTest (CSS), karena
 * pemotongan label terjadi di CSS, bukan di HTML.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin, 'admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    app()->setLocale('en');
});

it('membuka semua halaman grup Pengaturan Sistem', function (string $url, string $heading): void {
    get($url)->assertOk()->assertSee($heading);
})->with([
    ['/admin/website-mobile-customizer', 'Website, Android & iOS Customization'],
    ['/admin/tipografi', 'Typography'],
    ['/admin/maintenance', 'Website, Android & iOS Maintenance'],
    ['/admin/api-keys', 'API Key Management'],
    ['/admin/developer-studio', 'API & Developer Studio'],
    ['/admin/database-studio', 'Database Studio'],
    ['/admin/database-studio/create', 'Create Migration / Seeder / Factory'],
    ['/admin/terminal', 'Terminal'],
    ['/admin/developer-studio/create', 'Create Module'],
]);

it('tidak menamai halaman buat Studio Basis Data "Create Database Studio"', function (): void {
    get('/admin/database-studio/create')
        ->assertOk()
        ->assertSee('Create Migration / Seeder / Factory')
        ->assertDontSee('Create Database Studio');
});

it('tidak menamai halaman buat Studio API "Create API & Developer Studio"', function (): void {
    get('/admin/developer-studio/create')
        ->assertOk()
        ->assertSee('Create Module')
        ->assertDontSee('Create API &amp; Developer Studio')
        ->assertDontSee('Create API & Developer Studio');
});

it('tidak mengubah iOS menjadi IOS pada judul halaman maintenance', function (): void {
    $response = get('/admin/maintenance');

    $response->assertOk()
        ->assertSee('Website, Android & iOS Maintenance')
        ->assertDontSee('Website, Android & IOS Maintenance');

    expect($response->getContent())->not->toContain('&amp; IOS');
});

it('memakai label sidebar yang lengkap, bukan potongan', function (): void {
    get('/admin/api-keys')
        ->assertOk()
        ->assertSee('Website, Android & iOS Customization')
        ->assertSee('Website, Android & iOS Maintenance')
        ->assertSee('API & Developer Studio')
        ->assertSee('Database Studio');
});

it('memakai label halaman di breadcrumb, bukan slug mentah', function (): void {
    // Fallback lama: ucwords(slug) -> "Website Mobile Customizer" dan
    // "Tipografi" (tidak ikut diterjemahkan). Label yang benar datang dari
    // class halaman/resource-nya.
    $this->from('/admin/website-mobile-customizer')
        ->get('/admin/tipografi')
        ->assertOk()
        ->assertDontSee('Website Mobile Customizer');

    $this->from('/admin/tipografi')
        ->get('/admin/website-mobile-customizer')
        ->assertOk()
        ->assertSee('Website, Android & iOS Customization')
        ->assertDontSee('Tipografi');
});

it('memakai label halaman Indonesia di breadcrumb', function (): void {
    $this->from('/admin/tipografi?locale=id')
        ->get('/admin/website-mobile-customizer?locale=id')
        ->assertOk()
        ->assertSee('Kustomisasi Website, Mobile Android &amp; iOS', false)
        ->assertDontSee('Website Mobile Customizer');
});
