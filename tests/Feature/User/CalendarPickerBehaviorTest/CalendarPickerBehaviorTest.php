<?php

use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer']);
    Role::firstOrCreate(['name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

it('renders the birth place calendar with semiboldAll and no minDate', function () {
    // Dulu ada dua duplikat: satu di halaman Sign Up, satu di Complete Profile.
    // Pendaftaran dinonaktifkan (Google-only), jadi Complete Profile adalah
    // satu-satunya tempat field ini dirender -- dan itu harus tetap punya
    // semiboldAll (label tebal) DAN minDate null, karena tempat & tanggal lahir
    // boleh di masa lalu.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/complete-profile');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString(
        'semiboldAll: true',
        $html,
        'complete-profile birth_date must have semiboldAll: true'
    );

    $this->assertStringContainsString(
        'minDate: null',
        $html,
        'complete-profile birth_date must have minDate: null (all dates selectable)'
    );

    $this->assertStringContainsString('birth_place_date', $html);
});

it('renders checkout calendar (CheckoutCalendarPicker) with minDate and no semiboldAll', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $product = \App\Models\Product\Product::factory()->create(['vendor_id' => null]);

    $url = \App\Filament\User\Resources\ProductResource\ProductResource::getUrl('checkout', ['record' => $product->id]);

    $response = $this->actingAs($user)->get($url);

    $response->assertStatus(200);

    $html = $response->getContent();

    $today = now()->startOfDay()->format('Y-m-d');

    $this->assertStringContainsString(
        'checkoutCalendarPicker({',
        $html,
        'checkout must use the CheckoutCalendarPicker component'
    );

    $this->assertStringContainsString(
        'minDate: config.minDate',
        $html,
        'checkout JS must bind config.minDate so past dates get frozen'
    );

    $this->assertMatchesRegularExpression(
        '/minDate:\s*["\']' . preg_quote($today, '/') . '["\']/',
        $html,
        "checkout calendar must have minDate set to today ($today)"
    );

    $this->assertStringNotContainsString(
        'semiboldAll',
        $html,
        'checkout calendar must NOT expose semiboldAll'
    );

    $this->assertStringContainsString('booking_date', $html);
});
