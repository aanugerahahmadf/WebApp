<?php

use App\Models\User\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('home page (welcome) is accessible', function () {
    get('/')
        ->assertRedirect('/welcome/home');

    get('/welcome/home')
        ->assertStatus(200);
});

test('visitor can access welcome product list', function () {
    get('/welcome/flowerdecorationscatalog')
        ->assertStatus(200);
});

test('visitor can access welcome package list', function () {
    get('/welcome/flowerdecorationspackagecatalog')
        ->assertStatus(200);
});

test('guest can access welcome storefront root', function () {
    get('/welcome')
        ->assertRedirect('/welcome/home');
});

test('signed in customer is sent to user home from root', function () {
    $user = User::factory()->create();

    actingAs($user)->get('/')
        ->assertRedirect('/user/home');
});

test('signed in super admin is sent to user home from root', function () {
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user)->get('/')
        ->assertRedirect('/user/home');
});

test('logout sends back to welcome home', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('filament.user.auth.logout'))
        ->assertRedirect('/welcome/home');
});
