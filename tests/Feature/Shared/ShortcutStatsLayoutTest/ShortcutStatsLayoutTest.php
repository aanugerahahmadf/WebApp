<?php

use App\Filament\User\Widgets\ShortcutStats\ShortcutStats as UserShortcutStats;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats as WelcomeShortcutStats;
use App\Models\User\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    // `super_admin` dibuat di guard `web` karena user di bawah adalah
    // `App\Models\User\User`, dan `assignRole()` mencari role di guard model
    // itu sendiri. Role versi `admin` milik tabel `admins` tetap dibuat juga
    // supaya barisnya ada untuk kasus panel lain.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user, 'web');
});

/**
 * Lima kartu ada, semuanya hidup di dalam SATU track yang bisa digeser.
 *
 * JUMLAH KARTU SEKARANG LIMA, bukan enam.
 *
 * Kartu "Package Bunga" DIHAPUS dari widget `ShortcutStats` mengikuti refactor
 * `Product` -> `Catalog`: `PackageResource` tidak ada lagi, dan `Package`
 * sekarang hanya tier dari katalog -- tidak ada daftar paket yang bisa
 * dihitung. Test yang masih mengharapkan enam hanya menjaga UI lama.
 *
 * Daftar kartu yang ada sekarang: Catalog Bunga, Pesanan Saya, Favorit,
 * Voucher Aktif, Keranjang. Kalau kartu paket mau dikembalikan, kembalikan
 * juga di `ShortcutStats::getStats()` -- bukan hanya di test ini.
 *
 * Jumlah kartu per halaman itu urusan tiap permukaan dan diuji di
 * `ShortcutStatsSlideAnimationTest`; yang diuji di sini hanya tidak adanya kartu
 * yang hilang atau terpecah jadi beberapa track.
 */
function assertCardsInOneTrack(string $html): void
{
    expect(substr_count($html, 'home-stat-card'))->toBe(5, 'Harus ada 5 kartu: Catalog Bunga, Pesanan Saya, Favorit, Voucher Aktif, Keranjang')
        ->and($html)->toContain('shortcut-stats-track')
        ->and($html)->toContain('scroll-snap-type: x mandatory')
        // Inline style WAJIB display:flex. Kalau masih grid, kartu tetap 4
        // kolom -- flex-basis diabaikan di grid, dan inilah bug yang pernah
        // terjadi (blade sudah benar, CSS belum terpakai).
        ->and($html)->toContain('display: flex')
        ->and($html)->not->toContain('grid-template-columns');
}

test('welcome shortcut stats: lima kartu dalam satu track yang bisa digeser', function (): void {
    assertCardsInOneTrack(Livewire::test(WelcomeShortcutStats::class)->html());
});

test('user shortcut stats: lima kartu dalam satu track yang bisa digeser', function (): void {
    assertCardsInOneTrack(Livewire::test(UserShortcutStats::class)->html());
});

/*
 * Urutan kartu itu keputusan produk, bukan kebetulan: kartu katalog
 * ("Catalog Bunga") deliberate di depan karena itu pintu masuk belanja, lalu
 * disusul kartu milik user.
 *
 * "Package Bunga" TIDAK ada di daftar ini lagi -- kartu itu dihapus dari
 * `ShortcutStats::getStats()` mengikuti refactor `Product` -> `Catalog`. Test
 * lama masih mencarinya, jadi `expect($matched)->toBe(1)` gagal untuk label itu
 * padahal tidak ada apa pun yang rusak. Lihat `assertCardsInOneTrack()`.
 *
 * Karena view-nya carousel scroll-snap, urutan ini persis urutan swipe di
 * layar -- jadi kalau berubah diam-diam, yang berubah adalah yang dilihat
 * pengguna pertama. Karena itu urutannya dikunci di sini, bukan cuma
 * "labelnya ada semua".
 */
test('kartu katalog di depan, kartu user menyusul', function (string $widget): void {
    $html = Livewire::test($widget)->html();

    $order = ['Catalog Bunga', 'Pesanan Saya', 'Favorit', 'Voucher Aktif', 'Keranjang'];

    $found = [];
    foreach ($order as $label) {
        // Label dirender di dalam span berlabel stat-label, jadi itu yang
        // dicari -- bukan teks mentah, supaya kemunculan di teks lain
        // (ramo filter, tooltip) tidak ikut terhitung.
        $matched = preg_match(
            '/fi-wi-stats-overview-stat-label[^>]*>\s*'.preg_quote($label, '/').'\s*</',
            $html,
            $m,
            PREG_OFFSET_CAPTURE
        );

        expect($matched)->toBe(1, "Label '{$label}' tidak ada di markup widget");

        $found[$label] = $m[0][1];
    }

    // Offset harus naik monoton: label sebelumnya selalu muncul lebih dulu.
    $sorted = $found;
    asort($sorted);

    expect(array_keys($sorted))->toBe($order, 'Urutan kartu berubah');
})->with([
    'welcome' => WelcomeShortcutStats::class,
    'user' => UserShortcutStats::class,
]);
