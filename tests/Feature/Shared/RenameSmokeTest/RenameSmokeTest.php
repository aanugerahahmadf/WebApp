<?php

/**
 * Smoke test hasil rename Login -> SignIn, Register -> SignUp,
 * Dashboard -> Home (folder, file, namespace, class, view).
 *
 * Sign Up kemudian DINONAKTIFKAN (Google-only) tanpa menghapus kodenya: class
 * dan view-nya tetap ada, hanya `->registration()` di UserPanelProvider yang
 * dikomentari. Test ini menjaga kedua sisi itu sekaligus -- kode tidak boleh
 * hilang, route register tidak boleh muncul.
 *
 * Yang TIDAK berubah dan tidak boleh berubah:
 * - nama route (filament.user.auth.login, filament.*.pages.home, ...)
 * - URL (/user/signin, /welcome/home, API /dashboard)
 * - base class Filament (Filament\Pages\Auth\Login, Filament\Pages\Dashboard)
 */

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

it('route auth user mengarah ke class SignIn yang baru, dengan SignUp kept but disabled', function (): void {
    $loginRoute = Filament::getPanel('user')->getLoginUrl();

    expect($loginRoute)->toContain('/user/signin');

    $this->get($loginRoute)->assertOk();

    expect(class_exists(App\Filament\User\Auth\SignIn\SignIn::class))->toBeTrue()
        ->and(class_exists(App\Filament\Admin\Auth\SignIn\SignIn::class))->toBeTrue()
        // Sign Up class dan view SENGAJA TIDAK DIHAPUS saat pendaftaran
        // dimatikan -- hanya `->registration()` di UserPanelProvider yang
        // dikomentari. Kalau file ini hilang, satu-satunya jalan menghidupkan
        // pendaftaran lagi adalah menulis ulang form 900 baris dari nol.
        ->and(class_exists(App\Filament\User\Auth\SignUp\SignUp::class))->toBeTrue()
        ->and(View::exists('User.auth.sign-up.sign-up'))->toBeTrue()
        ->and(class_exists('App\Filament\User\Auth\Login\Login'))->toBeFalse()
        ->and(class_exists('App\Filament\User\Auth\Register\Register'))->toBeFalse()
        ->and(class_exists('App\Filament\Admin\Auth\Login\Login'))->toBeFalse();
});

it('route home ketiga panel mengarah ke class Home yang baru', function (): void {
    expect(class_exists(App\Filament\User\Pages\Home\Home::class))->toBeTrue()
        ->and(class_exists(App\Filament\Admin\Pages\Home\Home::class))->toBeTrue()
        ->and(class_exists(App\Filament\Welcome\Pages\Home\Home::class))->toBeTrue()
        ->and(class_exists('App\Filament\User\Pages\Dashboard\Dashboard'))->toBeFalse()
        ->and(class_exists('App\Filament\Admin\Pages\Dashboard\Dashboard'))->toBeFalse()
        ->and(class_exists('App\Filament\Welcome\Pages\Dashboard\Dashboard'))->toBeFalse();

    expect(route('filament.user.pages.home'))->toContain('/user/home')
        ->and(route('filament.welcome.pages.home'))->toContain('/welcome/home');
});

it('view sign-in, auth landing, dan social-buttons baru semuanya ada', function (): void {
    foreach ([
        'User.auth.sign-in.sign-in',
        'User.auth.auth.auth',
        'User.social-buttons.agreement-checkboxes.agreement-checkboxes',
        'User.social-buttons.agreement-modal.agreement-modal',
        'User.social-buttons.auth-buttons.auth-buttons',
        'User.social-buttons.social-buttons.social-buttons',
        'Welcome.social-buttons.agreement-checkboxes.agreement-checkboxes',
        'Welcome.social-buttons.agreement-modal.agreement-modal',
        'Welcome.social-buttons.auth-buttons.auth-buttons',
        'Welcome.social-buttons.social-buttons.social-buttons',
    ] as $view) {
        expect(View::exists($view))->toBeTrue($view);
    }
});

it('panel user dan admin tidak punya route register, walau class SignUp masih ada', function (): void {
    // Kode pendaftaran sengaja disimpan (class + view SignUp masih di repo),
    // tapi tidak ada satu pun route register yang terdaftar -- jadi tidak ada
    // halaman pendaftaran yang bisa dijangkau lewat URL.
    expect(Route::has('filament.user.auth.register'))->toBeFalse()
        ->and(Route::has('filament.admin.auth.register'))->toBeFalse()
        ->and(class_exists(App\Filament\User\Auth\SignUp\SignUp::class))->toBeTrue();

    $this->get('/admin/register')->assertNotFound();
    // Door user tidak 404: ia redirect ke halaman auth, yang memuat tombol
    // Google -- satu-satunya jalur pendaftaran yang tersisa.
    $this->get('/user/signup')->assertRedirect(route('filament.user.auth.index'));
});
