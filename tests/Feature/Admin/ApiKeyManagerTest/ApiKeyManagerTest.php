<?php

use App\Livewire\Admin\ApiKeyManagerComponent\ApiKeyManagerComponent;
use App\Models\Admin\Admin;
use App\Models\ApiKey\ApiKey;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();

    $this->actingAs($this->admin, 'admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('mencabut key milik admin yang sedang login', function () {
    $token = $this->admin->createToken('Uji Cabut', ['*'], now()->addDay());

    Livewire::test(ApiKeyManagerComponent::class)
        ->call('revokeKey', $token->accessToken->id)
        ->assertOk();

    expect(ApiKey::query()->whereKey($token->accessToken->id)->exists())->toBeFalse();
});

it('menampilkan aksi Cabut di tabel dan menjalankannya lewat tabel', function () {
    $token = $this->admin->createToken('Uji Aksi Tabel', ['*'], now()->addDay());
    $record = $token->accessToken;

    Livewire::test(ApiKeyManagerComponent::class)
        ->assertTableActionExists('revoke')
        ->callTableAction('revoke', $record)
        ->assertHasNoTableActionErrors();

    expect(ApiKey::query()->whereKey($record->id)->exists())->toBeFalse();
});
