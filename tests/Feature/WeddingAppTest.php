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
    get('/welcome/products')
        ->assertStatus(200);
});

test('visitor can access welcome package list', function () {
    get('/welcome/packages')
        ->assertStatus(200);
});

test('guest can access welcome storefront root', function () {
    get('/welcome')
        ->assertRedirect('/welcome/home');
});
