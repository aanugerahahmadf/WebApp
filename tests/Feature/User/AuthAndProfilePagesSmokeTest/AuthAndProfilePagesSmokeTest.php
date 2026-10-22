<?php

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a working sign up page again', function () {
    // PERUBAHAAN PERILAKU. Test ini sebelumnya bernama "has no register page
    // anymore" dan mengharapkan `/signup` redirect ke `/auth` --
    // itu benar saat pendaftaran email/password dinonaktifkan.
    //
    // Pendaftaran sekarang AKTIF: `WelcomePanelProvider` memanggil
    // `->registration(SignUp::class)` dan `->registrationRouteSlug('signup')`,
    // jadi route `filament.welcome.auth.register` terdaftar dan halaman Sign Up
    // hidup di `/signup`. (Panel 'user' dilebur ke panel 'welcome' pada
    // 2026-10-21, jadi pemanggilnya ada di `WelcomePanelProvider` --
    // `UserPanelProvider` masih ada sebagai file, tapi tidak lagi terdaftar
    // di `bootstrap/providers.php`.) File
    // `app/Filament/User/Auth/SignUp` beserta view-nya masih utuh, jadi yang
    // berubah hanya pemanggilnya.
    //
    // Konsekuensinya ada di test lain juga: `HasAuthBreadcrumbs` tidak lagi
    // membuang crumb "Sign Up" sebagai path retired, dan menu topbar storefront
    // memuat item "Sign Up Sekarang". Lihat `tests/README.md`, bagian "Test yang
    // sengaja tidak ada" -- catatan tentang pendaftaran nonaktif di sana sudah
    // diperbarui (lihat bagian "Halaman Sign Up / Register sudah HIDUP lagi").
    $response = $this->get('/signup');

    $response->assertOk();
});

it('renders complete profile page without error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/complete-profile');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('face_scan_photo', $html, 'face scan upload missing');
    $this->assertStringContainsString('selfie_photo', $html, 'selfie upload missing');
    $this->assertStringContainsString('ktp_photo', $html, 'id photo upload missing');
    $this->assertStringContainsString('source_of_funds', $html, 'source of funds missing');
    $this->assertStringContainsString('income_range', $html, 'income range missing');
});

