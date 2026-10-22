<?php

use App\Models\Admin\Admin;
use App\Models\User\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        // Shopper + tamu. Satu-satunya guard yang punya token Sanctum, jadi
        // satu-satunya yang bisa dipakai API HP.
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Tim internal. Provider-nya `admins`, jadi `attempt()` di guard ini
        // hanya bisa menyentuh baris di tabel `admins` -- tidak ada baris di
        // `users` yang bisa dijangkau dari sini, dan sebaliknya lewat `web`.
        //
        // Ini yang membuat pemisahan tabel sama dengan pemisahan otentikasi.
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // Driver `active-admin` (bukan 'eloquent') = EloquentUserProvider +
        // penolakan akun nonaktif. Daftarkan di AppServiceProvider::boot().
        //
        // @see \App\Auth\ActiveAdminUserProvider
        'admins' => [
            'driver' => 'active-admin',
            'model' => env('AUTH_ADMIN_MODEL', Admin::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // Broker terpisah untuk tim internal, supaya "Lupa Kata Sandi?" di
        // /admin/signin tidak pernah mengirim token reset ke email yang punya
        // akun belanja. Tabel token-nya tetap sama, yang membedakan cuma
        // provider -- jadi token milik admin tidak akan pernah dicocokkan ke
        // baris `users` (dan sebaliknya).
        'admins' => [
            'provider' => 'admins',
            'table' => env('AUTH_ADMIN_PASSWORD_RESET_TOKEN_TABLE', 'admin_password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
