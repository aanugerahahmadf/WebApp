<?php

/**
 * Setiap nama component yang muncul di snapshot Livewire HARUS bisa
 * di-resolve ulang oleh registry.
 *
 * Livewire\Features\SupportReleaseTokens\ReleaseToken::verify() melakukan:
 *
 *     try {
 *         $componentClass = app(ComponentRegistry::class)->getClass($snapshot['memo']['name']);
 *     } catch (ComponentNotFoundException) {
 *         throw new LivewireReleaseTokenMismatchException;   // → HTTP 419
 *     }
 *
 * Pesan exception-nya sama dengan release token yang benar-benar berubah,
 * jadi keduanya tidak bisa dibedakan dari pesan. Akibatnya 419 muncul
 * tanpa petunjuk sama sekali -- dan karena HttpException 4xx tidak
 * di-report Laravel, log pun kosong.
 *
 * Test ini menutup kedua cabang sekaligus: nama yang tidak bisa di-resolve
 * dan token yang berubah.
 */

use Filament\Facades\Filament;
use Livewire\Mechanisms\ComponentRegistry;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;

/*
 * Nama-nama ini diambil dari snapshot yang benar-benar ada di respons
 * /welcome/home. Kalau ada component baru yang ditambahkan ke halaman Home,
 * nama barunya perlu masuk daftar ini juga.
 */
const HOME_COMPONENT_NAMES = [
    'app.filament.welcome.pages.home.home',
    'app.filament.welcome.widgets.combined-catalog-widget.combined-catalog-widget',
    'app.filament.welcome.widgets.shortcut-stats.shortcut-stats',
    'app.filament.welcome.widgets.stats-overview.stats-overview',
    'filament.livewire.global-search',
    'filament.livewire.notifications',
    'welcome.cbir-camera-button.cbir-camera-button',
];

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

it('bisa me-resolve semua nama component yang ada di halaman Home', function (string $name): void {
    // Kalau nama ini gagal di-resolve, ReleaseToken::verify() melempar
    // LivewireReleaseTokenMismatchException → HTTP 419.
    $class = app(ComponentRegistry::class)->getClass($name);

    expect($class)->toBeString()->not->toBeEmpty();
})->with(HOME_COMPONENT_NAMES);

it('tidak menghasilkan release token yang berbeda dari snapshot', function (string $name): void {
    $generated = ReleaseToken::generate(app(ComponentRegistry::class)->getClass($name));

    // Snapshot di halaman membawa "release":"a-a-a". Kalau ini berubah,
    // setiap tab yang masih terbuka akan langsung 419.
    expect($generated)->toBe('a-a-a');
})->with(HOME_COMPONENT_NAMES);