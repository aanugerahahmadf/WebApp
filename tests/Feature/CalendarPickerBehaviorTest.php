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

it('renders birth_date calendar with semiboldAll and no minDate', function () {
    $response = $this->get('/user/register');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString(
        'semiboldAll: true',
        $html,
        'birth_date calendar must have semiboldAll: true'
    );

    $this->assertStringContainsString(
        'minDate: null',
        $html,
        'birth_date calendar must have minDate: null (all dates selectable)'
    );

    $this->assertStringContainsString('birth_date', $html);
});

it('renders birth_date calendar on complete-profile with semiboldAll', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/complete-profile');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString(
        'semiboldAll: true',
        $html,
        'complete-profile birth_date must have semiboldAll: true'
    );
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
