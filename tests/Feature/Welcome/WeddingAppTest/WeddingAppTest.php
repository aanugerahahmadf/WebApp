<?php

use App\Models\User\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('home page (welcome) is accessible', function () {
    get('/')
        ->assertRedirect('/home');

    get('/home')
        ->assertStatus(200);
});

test('visitor can access welcome catalog list', function () {
    get('/flowerdecorationscatalog')
        ->assertStatus(200);
});

/*
 * Test "visitor can access welcome package list" DIHAPUS.
 *
 * `/welcome/flowerdecorationspackagecatalog` tidak ada lagi: `PackageResource`
 * dihapus saat penataan ulang `Product` -> `Catalog`, dan `Package` kini adalah
 * TIER dari katalog (`Catalog::tiers()`), bukan record yang bisa dijelajahi
 * sendiri.
 *
 * Test di atasnya -- daftar katalog -- masih menguji hal yang sama untuk
 * entitas yang benar-benar ada. Kalau halaman daftar paket dihidupkan lagi,
 * tambahkan route-nya lalu test ini bisa dikembalikan.
 */

test('guest can access welcome storefront root', function () {
    get('/welcome')
        ->assertRedirect('/home');
});

test('signed in customer is sent to user home from root', function () {
    $user = User::factory()->create();

    actingAs($user)->get('/')
        ->assertRedirect('/home');
});

test('signed in super admin is sent to user home from root', function () {
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user)->get('/')
        ->assertRedirect('/home');
});

test('logout sends back to welcome home', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('filament.welcome.auth.logout'))
        ->assertRedirect('/home');
});

