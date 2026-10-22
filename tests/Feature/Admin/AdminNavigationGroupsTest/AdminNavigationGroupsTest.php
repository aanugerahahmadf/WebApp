<?php

use App\Filament\Admin\Resources\HelpResource\HelpResource;
use App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource;
use App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource;
use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/*
 * Group navigasi panel admin.
 *
 * "Manajemen Legal" DIHAPUS (keputusan pengguna, 2026-10-22): isinya --
 * Help Center, Privacy Policy, Terms Of Service, Application Policy -- sudah
 * dipindahkan ke "Data Master".
 *
 * Kenapa dikunci dengan test:
 *
 *   Group navigasi yang dibuang dari `navigationGroups()` TIDAK otomatis
 *   hilang dari halaman -- Filament hanya menampilkan group yang PUNYA item.
 *   Jadi kalau `getNavigationGroup()` sebuah resource masih mengembalikan
 *   'Manajemen Legal', Filament akan MEMBUAT group itu sendiri lagi (label
 *   group yang dideklarasikan di provider tidak lagi jadi rujukan), dan
 *   sebuah group punyanyi muncul di navigasi tanpa jejak.
 *
 *   Hasilnya: navigasi panel admin punya "Legal Management" yang muncul
 *   sebagai group liar -- bukan lagi "Manajemen Legal" yang dihapus, tapi
 *   juga tidak pernah dikontrol.
 */
function adminNavigationGroupLabels(): array
{
    return collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group): string => (string) $group->getLabel())
        ->values()
        ->all();
}

it('tidak lagi punya group navigasi Manajemen Legal', function (): void {
    expect(adminNavigationGroupLabels())->not->toContain('Manajemen Legal');
});

it('memiliki lima group navigasi, tanpa Manajemen Legal', function (): void {
    // Beranda, Data Master, Transaksi, Pesan, Pengaturan Sistem.
    expect(adminNavigationGroupLabels())->toBe([
        'Beranda',
        'Data Master',
        'Transaksi',
        'Pesan',
        'Pengaturan Sistem',
    ]);
});

it('empat resource legal berada di group Data Master', function (string $resource): void {
    expect($resource::getNavigationGroup())->toBe('Data Master');
})->with([
    HelpResource::class,
    PrivacyPolicyResource::class,
    TermsOfServiceResource::class,
    WeddingDecorationPolicyResource::class,
]);

it('resource lain tidak ikut pindah', function (string $resource, string $group): void {
    expect($resource::getNavigationGroup())->toBe($group);
})->with([
    [\App\Filament\Admin\Resources\UserResource\UserResource::class, 'Data Master'],
    [\App\Filament\Admin\Resources\CatalogResource\CatalogResource::class, 'Data Master'],
    [\App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource::class, 'Data Master'],
    [\App\Filament\Admin\Resources\OrderResource\OrderResource::class, 'Transaksi'],
    [\App\Filament\Admin\Resources\ReviewResource\ReviewResource::class, 'Data Master'],
    [\App\Filament\Admin\Resources\ReportResource\ReportResource::class, 'Data Master'],
    [\App\Filament\Admin\Resources\VoucherResource\VoucherResource::class, 'Transaksi'],
    [\App\Filament\Admin\Resources\TransactionResource\TransactionResource::class, 'Transaksi'],
    // ApiKeyResource dihapus: halaman API Key sekarang adalah Page (bukan Resource).
]);

it('navigasi yang dirender tidak punya grup Legal Management', function (): void {
    $admin = makeAdminWithStorefrontIdentity();

    actingAs($admin, 'admin');

    $html = $this->get('/admin/edit-profile-page')->assertOk()->getContent();

    // Group "Manajemen Legal" tidak ada lagi. Bandingkan dengan label yang
    // SEDANG dipakai (locale test bisa 'en' atau 'id'), bukan string
    // hardcode -- supaya testnya tidak rapuh kalau locale default berubah.
    expect($html)->not->toContain(__('Manajemen Legal'));

    // Tapi isinya tetap ada, sekarang di dalam "Data Master".
    expect($html)->toContain(__('Data Master'))
        ->and($html)->toContain(HelpResource::getUrl())
        ->and($html)->toContain(PrivacyPolicyResource::getUrl())
        ->and($html)->toContain(TermsOfServiceResource::getUrl())
        ->and($html)->toContain(WeddingDecorationPolicyResource::getUrl());
});
