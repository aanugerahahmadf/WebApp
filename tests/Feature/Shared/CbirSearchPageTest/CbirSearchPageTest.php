<?php

use App\Models\User\User;
use Illuminate\Support\Facades\Http;

/**
 * Halaman "Pencarian Visual" (/cbir-search).
 *
 * Halaman ini tidak muncul di navigasi (`$shouldRegisterNavigation = false`)
 * karena pintunya adalah ikon kamera/galeri di pencarian global, jadi tidak ada
 * tautan yang mengarahkannya — kalau halamannya 500, tidak ada yang menyadari
 * sampai seseorang memakai kamera.
 *
 * Pencarian adalah fitur ber-akun (lihat `AuthenticateWelcome::PUBLIC_PATHS`),
 * jadi tamu memang diarahkan ke halaman masuk, dan yang diuji di sini adalah
 * keduanya: tamu ditolak dengan redirect, user yang sudah masuk melihat 200.
 */
beforeEach(function () {
    Http::fake(['*' => Http::response(['status' => 'healthy'])]);
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get('/cbir-search')->assertRedirect();
});

test('user yang sudah masuk melihat halaman pencarian visual', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cbir-search')
        ->assertOk()
        ->assertSee(__('Pencarian Visual'));
});

test('halaman pencarian visual bisa dibuka dalam mode galeri', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cbir-search?mode=gallery')
        ->assertOk();
});
