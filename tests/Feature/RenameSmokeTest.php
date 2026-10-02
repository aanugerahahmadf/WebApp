<?php

/**
 * Smoke test hasil rename Login -> SignIn, Register -> SignUp,
 * Dashboard -> Home (folder, file, namespace, class, view).
 *
 * Yang TIDAK berubah dan tidak boleh berubah:
 * - nama route (filament.user.auth.login, filament.*.pages.home, ...)
 * - URL (/user/signin, /user/signup, /welcome/home, API /dashboard)
 * - base class Filament (Filament\Pages\Auth\Login, Filament\Pages\Dashboard)
 */

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

it('route auth user mengarah ke class SignIn dan SignUp yang baru', function (): void {
    $loginRoute = Filament::getPanel('user')->getLoginUrl();
    $registerRoute = route('filament.user.auth.register');

    expect($loginRoute)->toContain('/user/signin')
        ->and($registerRoute)->toContain('/user/signup');

    $this->get($loginRoute)->assertOk();
    $this->get($registerRoute)->assertOk();

    expect(class_exists(App\Filament\User\Auth\SignIn\SignIn::class))->toBeTrue()
        ->and(class_exists(App\Filament\User\Auth\SignUp\SignUp::class))->toBeTrue()
        ->and(class_exists(App\Filament\Admin\Auth\SignIn\SignIn::class))->toBeTrue()
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

it('view sign-in, sign-up, dan social-buttons baru semuanya ada', function (): void {
    foreach ([
        'User.auth.sign-in.sign-in',
        'User.auth.sign-up.sign-up',
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

it('panel admin tidak punya route register', function (): void {
    $this->get('/admin/register')->assertNotFound();
});
