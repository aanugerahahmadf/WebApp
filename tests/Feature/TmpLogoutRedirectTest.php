<?php

use App\Models\User\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(fn () => seed());

test('logout panel user -> welcome/home', function (): void {
    $user = User::factory()->create();

    actingAs($user, 'web')->post('/user/logout')->assertRedirect('/welcome/home');
});

test('logout panel admin -> welcome/home', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    actingAs($user, 'web')->post('/admin/logout')->assertRedirect('/welcome/home');
});

test('logout panel welcome -> welcome/home', function (): void {
    $user = User::factory()->create();

    actingAs($user, 'web')->post('/welcome/logout')->assertRedirect('/welcome/home');
});
