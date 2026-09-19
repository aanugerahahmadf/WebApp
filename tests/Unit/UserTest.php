<?php

use App\Models\User\User;

test('it can get the filament name', function (): void {
    $user = User::factory()->make([
        'full_name' => 'Ahmad Anugerah',
        'username' => 'aanugerah',
    ]);

    expect($user->getFilamentName())->toBe('Ahmad Anugerah');
});

test('it uses username as filament name fallback', function (): void {
    $user = User::factory()->make([
        'full_name' => null,
        'username' => 'aanugerah',
    ]);

    expect($user->getFilamentName())->toBe('aanugerah');
});

test('it casts active_status to boolean', function (): void {
    $user = User::factory()->make(['active_status' => 1]);

    expect($user->active_status)->toBeTrue();

    $user->active_status = 0;
    expect($user->active_status)->toBeFalse();
});

test('it hides sensitive attributes', function (): void {
    $user = User::factory()->make();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
});
