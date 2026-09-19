<?php

use App\Models\User\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('home page (welcome) is accessible', function () {
    get('/')
        ->assertStatus(200)
        ->assertSee('Wedding');
});

test('user can access product list', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/user/products')
        ->assertStatus(200);
});

test('user can access package list', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/user/packages')
        ->assertStatus(200);
});

test('guest is redirected when accessing user panel', function () {
    get('/user')
        ->assertStatus(302);
});
