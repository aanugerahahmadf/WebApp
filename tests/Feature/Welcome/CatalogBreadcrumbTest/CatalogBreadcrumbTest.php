<?php

/**
 * Breadcrumb katalog harus menyebut tier paket, bukan berhenti di nama katalog.
 *
 * Halaman checkout dulu hanya menampilkan "Beranda > Katalog Dekorasi Bunga >
 * nama katalog". Tier yang dipilih di halaman detail hilang karena URL checkout
 * tidak membawanya dan breadcrumb-nya memakai trail bawaan Filament. Test ini
 * mengunci trail lengkapnya:
 *
 *     Beranda > Katalog Dekorasi Bunga > nama katalog > nama tier > Checkout
 */

use App\Filament\User\Resources\CatalogResource\Pages\CheckoutCatalog\CheckoutCatalog as UserCheckout;
use App\Filament\User\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Filament\Welcome\Resources\CatalogResource\Pages\CheckoutCatalog\CheckoutCatalog as WelcomeCheckout;
use App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog as WelcomeView;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
    actingAs($this->customer, 'web');

    $this->catalog = Catalog::factory()->create([
        'stock' => 5,
        'name_translations' => [
            'id' => 'Paket Gaya Resort Bali',
            'en' => 'Bali Resort Style Package',
        ],
    ]);

    $this->standardTier = Package::factory()->forCatalog($this->catalog)->create([
        'sort_order' => 1,
        'name_translations' => [
            'id' => 'Paket Standar',
            'en' => 'Standard Flower Decoration Package',
        ],
    ]);

    $this->premiumTier = Package::factory()->forCatalog($this->catalog)->create([
        'sort_order' => 2,
        'name_translations' => [
            'id' => 'Gebyok Ukir Kayu Premium',
            'en' => 'Premium Carved Teak Gebyok',
        ],
    ]);
});

test('breadcrumb checkout berisi katalog, tier yang dipilih, dan checkout', function (): void {
    $breadcrumbs = array_values(
        Livewire::withQueryParams(['tier' => $this->premiumTier->getKey()])
            ->test(WelcomeCheckout::class, ['record' => $this->catalog->getKey()])
            ->instance()
            ->getBreadcrumbs()
    );

    expect($breadcrumbs)->toBe([
        __('Beranda'),
        __('Katalog Dekorasi Bunga'),
        __($this->catalog->name),
        $this->premiumTier->name,
        __('Checkout'),
    ]);
});

test('halaman checkout merender breadcrumb lengkap dengan nama tier', function (): void {
    $this->get("/flowerdecorationscatalog/{$this->catalog->getKey()}/checkout?tier={$this->premiumTier->getKey()}")
        ->assertOk()
        ->assertSeeInOrder([
            __('Beranda'),
            __('Katalog Dekorasi Bunga'),
            __($this->catalog->name),
            $this->premiumTier->name,
            __('Checkout'),
        ]);
});

test('breadcrumb checkout tetap menyebut tier walau URL dibuka tanpa parameter tier', function (): void {
    $breadcrumbs = array_values(
        Livewire::test(WelcomeCheckout::class, ['record' => $this->catalog->getKey()])
            ->instance()
            ->getBreadcrumbs()
    );

    // Tanpa `?tier=`, tier pertama (Standar) dipakai sebagai fallback supaya
    // nama paket tidak pernah hilang dari breadcrumb.
    expect($breadcrumbs[3])->toBe($this->standardTier->name)
        ->and($breadcrumbs[4])->toBe(__('Checkout'));
});

test('breadcrumb checkout mengabaikan tier milik katalog lain', function (): void {
    $otherCatalog = Catalog::factory()->create(['stock' => 5]);
    $foreignTier = Package::factory()->forCatalog($otherCatalog)->create();

    $breadcrumbs = array_values(
        Livewire::withQueryParams(['tier' => $foreignTier->getKey()])
            ->test(WelcomeCheckout::class, ['record' => $this->catalog->getKey()])
            ->instance()
            ->getBreadcrumbs()
    );

    expect($breadcrumbs[3])->toBe($this->standardTier->name)
        ->and($breadcrumbs)->not->toContain($foreignTier->name);
});

test('breadcrumb detail katalog menampilkan tier yang sedang dipilih', function (): void {
    $breadcrumbs = array_values(
        Livewire::withQueryParams(['tier' => $this->premiumTier->getKey()])
            ->test(WelcomeView::class, ['record' => $this->catalog->getKey()])
            ->instance()
            ->getBreadcrumbs()
    );

    expect($breadcrumbs)->toBe([
        __('Beranda'),
        __('Katalog Dekorasi Bunga'),
        __($this->catalog->name),
        $this->premiumTier->name,
    ]);
});

test('tombol Pesan Sekarang membawa tier terpilih ke URL checkout', function (): void {
    $component = Livewire::test(WelcomeView::class, ['record' => $this->catalog->getKey()])
        ->set('selectedTierId', $this->premiumTier->getKey());

    $component->callInfolistAction('.buy_now_detailAction', 'buy_now_detail');

    $component->assertRedirect(
        CatalogResource::getUrl('checkout', [
            'record' => $this->catalog->getKey(),
            'tier' => $this->premiumTier->getKey(),
        ])
    );
});

test('tautan Pesan Sekarang di panel User juga membawa tier terpilih', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    Livewire::test(ViewCatalog::class, [
        'record' => $this->catalog->getKey(),
    ])
        ->set('selectedTierId', $this->premiumTier->getKey())
        ->assertSee('?tier='.$this->premiumTier->getKey(), escape: false);
});

test('breadcrumb checkout di panel User juga menyebut tier', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $breadcrumbs = array_values(
        Livewire::withQueryParams(['tier' => $this->premiumTier->getKey()])
            ->test(UserCheckout::class, ['record' => $this->catalog->getKey()])
            ->instance()
            ->getBreadcrumbs()
    );

    expect($breadcrumbs[3])->toBe($this->premiumTier->name)
        ->and($breadcrumbs[4])->toBe(__('Checkout'));
});
