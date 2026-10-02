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

function assertFourAcross(string $html): void
{
    // Kolom ditulis inline !important di view sendiri (Shared.widgets.shortcut-stats),
    // jadi tidak bisa ditimpa stylesheet / breakpoint / platform mana pun.
    expect($html)->toContain('grid-template-columns: repeat(4, minmax(0, 1fr))')
        ->and(substr_count($html, 'home-stat-card'))->toBe(4, 'Harus ada 4 kartu: My Orders, Favorite, Active Voucher, Cart');
}

test('welcome shortcut stats: empat kartu sebaris satu row', function (): void {
    assertFourAcross(Livewire::test(WelcomeShortcutStats::class)->html());
});

test('user shortcut stats: empat kartu sebaris satu row', function (): void {
    assertFourAcross(Livewire::test(UserShortcutStats::class)->html());
});
