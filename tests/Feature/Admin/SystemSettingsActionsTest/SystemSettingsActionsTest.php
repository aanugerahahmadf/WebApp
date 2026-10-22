<?php

use App\Livewire\Admin\FontSettingsComponent\FontSettingsComponent;
use App\Livewire\Admin\WebsiteMobileCustomizerComponent\WebsiteMobileCustomizerComponent;
use App\Models\Admin\Admin;
use App\Models\FmAppSetting\FmAppSetting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Halaman Pengaturan Sistem bukan cuma harus TERBUKA -- tombol simpannya
 * harus benar-benar menulis ke `fm_app_settings`.
 *
 * Dua halaman yang punya form sendiri (bukan resource Filament):
 *
 *   - /admin/tipografi                 -> FontSettingsComponent
 *   - /admin/website-mobile-customizer -> WebsiteMobileCustomizerComponent
 *
 * Keduanya memakai `$this->form->getState()`, jadi kalau state path atau
 * nama field berubah, halamannya tetap 200 tapi tidak ada yang tersimpan.
 * Test ini yang menangkapnya.
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

it('menyimpan form Tipografi ke fm_app_settings', function (): void {
    Livewire::test(FontSettingsComponent::class)
        ->fillForm([
            'size' => 18,
            'weight' => 600,
            'transform' => 'uppercase',
            'color' => '#123456',
            'accent_color' => '#654321',
            'bg_color' => '',
            'radius' => 12,
            'brand_name' => 'Brand Tipografi Uji',
            'brand_logo' => '/images/logo.png',
            'custom_css' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $appearance = FmAppSetting::appearance();

    expect($appearance['size'])->toBe(18)
        ->and($appearance['weight'])->toBe(600)
        ->and($appearance['transform'])->toBe('uppercase')
        ->and($appearance['color'])->toBe('#123456')
        ->and($appearance['accent_color'])->toBe('#654321')
        ->and($appearance['radius'])->toBe(12)
        ->and($appearance['brand_name'])->toBe('Brand Tipografi Uji');
});

it('menyimpan form Kustomisasi Website & Mobile ke fm_app_settings', function (): void {
    Livewire::test(WebsiteMobileCustomizerComponent::class)
        ->fillForm([
            'appearance' => [
                'brand_name' => 'Brand Customizer Uji',
                'size' => 18,
                'radius' => 12,
            ],
            'mobile_flutter' => [
                'app_name' => 'Aplikasi Flutter Uji',
            ],
            'maintenance' => [
                'flutter_android' => [
                    'is_active' => true,
                    'message' => 'Sedang pemeliharaan uji.',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $appearance = FmAppSetting::appearance();

    expect($appearance['brand_name'])->toBe('Brand Customizer Uji')
        ->and($appearance['size'])->toBe(18)
        ->and($appearance['radius'])->toBe(12);

    expect(FmAppSetting::where('key', 'mobile_flutter')->value('value')['app_name'])
        ->toBe('Aplikasi Flutter Uji');

    $maintenance = FmAppSetting::where('key', 'maintenance_mobile_flutter_android')->value('value');

    expect($maintenance['is_active'])->toBeTrue()
        ->and($maintenance['message'])->toBe('Sedang pemeliharaan uji.');
});
