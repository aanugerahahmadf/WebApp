<?php

namespace App\Http\Middleware\VerifyCsrfToken;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'admin/*',
        'livewire/*',

        // Logout itu idempotent (terburuk: korban ke-logout orang).
        // Tanpa ini, sesi kedaluwarsa bikin klik SignOut mendarat di 419
        // PAGE EXPIRED alih-alih welcome/home.
        'user/logout',
        'welcome/logout',

        'api/v1.0/payment/notify',
        'api/webhooks/fonnte',
        'api/webhooks/fonnte/connect',
        'api/webhooks/fonnte/status',
    ];
}
