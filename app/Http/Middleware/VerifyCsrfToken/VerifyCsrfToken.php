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
        'api/db-proxy',

        'api/v1.0/payment/notify',
        'api/webhooks/fonnte',
        'api/webhooks/fonnte/connect',
        'api/webhooks/fonnte/status',
    ];
}
