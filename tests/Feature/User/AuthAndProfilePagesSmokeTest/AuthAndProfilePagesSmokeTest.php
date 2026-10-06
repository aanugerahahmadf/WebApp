<?php

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has no register page anymore, the old url redirects to the auth landing', function () {
    // Form pendaftaran dinonaktifkan: tidak ada halaman Sign Up yang terbuka, jadi field yang
    // hanya ada di sana (first/mid/last name, upload KTP/selfie/avatar) kini
    // dipin di halaman Complete Profile -- jalur satu-satunya untuk mengisi
    // data identitas setelah akun dibuat lewat Google.
    $response = $this->get('/user/signup');

    $response->assertRedirect('/user/auth');
    $response->assertDontSee('first_name');
});

it('renders complete profile page without error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/complete-profile');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('face_scan_photo', $html, 'face scan upload missing');
    $this->assertStringContainsString('selfie_photo', $html, 'selfie upload missing');
    $this->assertStringContainsString('ktp_photo', $html, 'id photo upload missing');
    $this->assertStringContainsString('source_of_funds', $html, 'source of funds missing');
    $this->assertStringContainsString('income_range', $html, 'income range missing');
});
