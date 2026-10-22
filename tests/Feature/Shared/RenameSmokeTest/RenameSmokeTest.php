<?php

/**
 * Smoke test hasil rename Login -> SignIn, Register -> SignUp,
 * Dashboard -> Home (folder, file, namespace, class, view).
 *
 * Sign Up sekarang AKTIF LAGI: `->registration(SignUp::class)` +
 * `->registrationRouteSlug('signup')` dipanggil di WelcomePanelProvider, jadi
 * route `filament.welcome.auth.register` terdaftar dan URL utamanya `/signup`.
 * URL lama `/user/register` dilayani redirect di routes/web/web.php.
 * Panel admin tetap Google-only -- tidak ada `->registration()` di sana.
 *
 * Yang TIDAK berubah dan tidak boleh berubah:
 * - nama CLASS (SignIn, SignUp, Home) dan URL (/signin, /signup, /home)
 * - base class Filament (Filament\Pages\Auth\Login, Filament\Pages\Dashboard)
 *
 * Yang BERUBAH (merge panel user -> panel welcome):
 * - prefix NAMA ROUTE: filament.user.* -> filament.welcome.*
 */

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Panel 'user' sudah dilebur ke panel 'welcome'. `getPanel('user')`
    // sekarang diam-diam mengembalikan panel default (welcome), jadi panel
    // yang dipakai eksplisit di sini supaya tidak bergantung pada fallback.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

it('route auth storefront mengarah ke class SignIn yang baru, dengan SignUp aktif', function (): void {
    $loginRoute = Filament::getPanel('welcome')->getLoginUrl();

    expect($loginRoute)->toContain('/signin');

    $this->get($loginRoute)->assertOk();

    expect(class_exists(App\Filament\User\Auth\SignIn\SignIn::class))->toBeTrue()
        ->and(class_exists(App\Filament\Admin\Auth\SignIn\SignIn::class))->toBeTrue()
        // Sign Up class dan view dipakai lagi sekarang pendaftaran dinyalakan
        // lewat `->registration(SignUp::class)`. Kalau file ini hilang, satu-
        // satunya jalan menghidupkan pendaftaran adalah menulis ulang form
        // 900 baris dari nol.
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

    expect(route('filament.welcome.pages.home'))->toContain('/home')
        // Class Home milik panel user MASIH hidup, tapi panel 'user' sudah
        // dilebur ke panel 'welcome' -- jadi route home-nya ada di bawah
        // prefix 'welcome'. Class itu harus tetap bisa_generate URL home
        // yang benar kalau dipanggil dengan panel 'welcome'.
        ->and(App\Filament\User\Pages\Home\Home::getUrl(panel: 'welcome'))->toContain('/home');
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

it('pendaftaran aktif di panel storefront, tetap mati di panel admin', function (): void {
    // Pendaftaran user dinyalakan: `->registration(SignUp::class)` +
    // `->registrationRouteSlug('signup')` di WelcomePanelProvider, jadi route
    // `filament.welcome.auth.register` terdaftar dan URL utamanya `/signup`.
    //
    // Prefix route berubah dari 'user' ke 'welcome' karena panel 'user'
    // dilebur ke panel 'welcome' -- nama CLASS dan URL /signup tidak berubah.
    expect(Route::has('filament.welcome.auth.register'))->toBeTrue()
        // Panel admin tetap Google-only -- tidak ada `->registration()` di sana.
        ->and(Route::has('filament.admin.auth.register'))->toBeFalse()
        ->and(class_exists(App\Filament\User\Auth\SignUp\SignUp::class))->toBeTrue();

    $this->get('/admin/register')->assertNotFound();

    // URL lama tetap dilayani redirect oleh routes/web/web.php.
    $this->get('/user/register')->assertRedirect('/signup');
    $this->get('/register')->assertRedirect('/signup');
    // Halaman pendaftaran user hidup dan merender form-nya.
    $this->get('/signup')->assertOk()->assertSee('sign-up');
});

