<?php

use App\Filament\User\Widgets\ShortcutStats\ShortcutStats as UserShortcutStats;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats as WelcomeShortcutStats;
use App\Models\User\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user, 'web');
});

function assertFourCardsInOneTrack(string $html): void
{
    // Keempat kartu tetap ada, dan semuanya hidup di dalam SATU track yang
    // bisa digeser. Jumlah kartu per halaman itu urusan tiap permukaan dan
    // diuji di ShortcutStatsSlideAnimationTest; test ini hanya memastikan
    // tidak ada kartu yang hilang atau terpecah jadi beberapa track.
    expect(substr_count($html, 'home-stat-card'))->toBe(4, 'Harus ada 4 kartu: Pesanan Saya, Favorit, Voucher Aktif, Keranjang')
        ->and($html)->toContain('shortcut-stats-track')
        ->and($html)->toContain('scroll-snap-type: x mandatory')
        // Inline style WAJIB display:flex. Kalau masih grid, kartu tetap 4
        // kolom -- flex-basis diabaikan di grid, dan inilah bug yang pernah
        // terjadi (blade sudah benar, CSS belum terpakai).
        ->and($html)->toContain('display: flex')
        ->and($html)->not->toContain('grid-template-columns');
}

test('welcome shortcut stats: empat kartu dalam satu track yang bisa digeser', function (): void {
    assertFourCardsInOneTrack(Livewire::test(WelcomeShortcutStats::class)->html());
});

test('user shortcut stats: empat kartu dalam satu track yang bisa digeser', function (): void {
    assertFourCardsInOneTrack(Livewire::test(UserShortcutStats::class)->html());
});
