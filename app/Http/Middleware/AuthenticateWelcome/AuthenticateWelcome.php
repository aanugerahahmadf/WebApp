<?php

namespace App\Http\Middleware\AuthenticateWelcome;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Auth gate for the 'welcome' panel, which doubles as the public storefront.
 *
 * Filament's own Authenticate middleware sends every unauthenticated visitor to
 * the panel's login page, and the welcome panel does not register one -- so
 * `/welcome` used to be unreachable for guests (AuthenticationException with no
 * redirect target). The storefront is meant to be browsable before signing in,
 * the way the reference site behaves, so this middleware keeps the gate but
 * punches a hole for the public paths below.
 *
 * Everything else (cart, orders, wishlist, messages, settings, profile, search,
 * logout) is still closed to guests, and a guest who lands on one of those is
 * redirected to the user panel login, which does have a login page. The
 * intended URL is kept in the session by Laravel's exception handler, so the
 * guest resumes where they were after signing in.
 *
 * Registered as a Livewire persistent middleware in AppServiceProvider, because
 * this class replaces Filament's Authenticate in the panel's authMiddleware --
 * without that, a Livewire update on a protected page would skip the gate.
 */
class AuthenticateWelcome extends Authenticate
{
    /**
     * Panel-relative paths a guest may open.
     *
     * The storefront and the catalog: the landing page (panel root + its
     * dashboard) and the product / package index and detail pages. Everything
     * account-shaped is deliberately absent.
     *
     * @var list<string>
     */
    public const PUBLIC_PATHS = [
        'welcome',
        'welcome/home',
        'welcome/products',
        'welcome/products/*',
        'welcome/packages',
        'welcome/packages/*',
        'welcome/messages',
        'welcome/messages/*',
    ];

    public const LOGIN_ROUTE = 'filament.user.auth.login';

    /**
     * Filament guards every panel route with this, so a guest request either
     * falls through to the public paths above or is turned away here.
     *
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        if (Filament::auth()->check()) {
            // Same panel-access check Filament performs, so swapping the
            // middleware in does not loosen who may enter the panel.
            $panel = Filament::getCurrentPanel();

            $this->auth->shouldUse($panel->getAuthGuard());

            $user = Filament::auth()->user();

            abort_if(
                $user instanceof FilamentUser
                    ? (! $user->canAccessPanel($panel))
                    : (config('app.env') !== 'local'),
                403,
            );

            return;
        }

        if ($this->isPublic($request)) {
            return;
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * The welcome panel has no login page of its own; the user panel owns the
     * auth pages, and the whole reason this middleware exists is to end up
     * there.
     */
    protected function redirectTo($request): ?string
    {
        return route(self::LOGIN_ROUTE);
    }

    protected function isPublic(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (self::PUBLIC_PATHS as $publicPath) {
            if (Str::is($publicPath, $path)) {
                return true;
            }
        }

        return false;
    }
}
