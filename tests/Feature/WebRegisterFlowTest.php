<?php

use App\Filament\User\Auth\Register\Register;
use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer']);
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

it('registers a new user through the panel form', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'username' => 'john.doe',
            'email' => 'john.doe@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'first_name' => 'John',
            'mid_name' => 'Adam',
            'last_name' => 'Doe',
            'whatsapp' => '8123456789',
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-05-12',
            'country' => 'Indonesia',
            'address' => 'Jl. Merdeka No. 1, Jakarta',
            'gender' => 'Pria',
            'occupation' => 'Karyawan',
            'agreement' => true,
            'remember' => true,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'john.doe@example.com',
        'username' => 'john.doe',
        'first_name' => 'John',
        'mid_name' => 'Adam',
        'last_name' => 'Doe',
        'full_name' => 'John Adam Doe',
        'whatsapp' => '8123456789',
        'birth_place' => 'Jakarta',
        'country' => 'Indonesia',
        'address' => 'Jl. Merdeka No. 1, Jakarta',
        'gender' => 'Pria',
        'occupation' => 'Karyawan',
        'liveness_completed' => false,
    ]);

    $user = User::where('email', 'john.doe@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->birth_date->format('Y-m-d'))->toBe('1990-05-12');
    expect(Hash::check('StrongPass123!', $user->password))->toBeTrue();
    expect($user->hasRole('customer'))->toBeTrue();
});

it('rejects registration when agreement is not checked', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'username' => 'jane.doe',
            'email' => 'jane.doe@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'whatsapp' => '8123456789',
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-05-12',
            'country' => 'Indonesia',
            'address' => 'Jl. Merdeka No. 1, Jakarta',
            'gender' => 'Wanita',
            'occupation' => 'Karyawan',
            'agreement' => false,
            'remember' => true,
        ])
        ->call('register')
        ->assertHasFormErrors(['agreement']);

    $this->assertDatabaseCount('users', 0);
});

it('saves the complete profile from the livewire component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CompleteProfileComponent::class)
        ->fillForm([
            'first_name' => 'Jane',
            'mid_name' => 'Ann',
            'last_name' => 'Doe',
            'whatsapp' => '8123456789',
            'birth_place' => 'Bandung',
            'birth_date' => '1992-08-20',
            'country' => 'Indonesia',
            'address' => 'Jl. Braga No. 2, Bandung',
            'gender' => 'Wanita',
            'occupation' => 'Wiraswasta',
        ])
        ->call('save')
        ->assertRedirect(route('filament.user.pages.home'));

    $user->refresh();

    expect($user->full_name)->toBe('Jane Ann Doe');
    expect($user->whatsapp)->toBe('8123456789');
    expect($user->birth_place)->toBe('Bandung');
    expect($user->liveness_completed)->toBeFalse();
});
