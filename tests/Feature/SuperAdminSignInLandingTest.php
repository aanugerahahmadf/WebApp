<?php

/**
 * Super admin yang masuk lewat halaman Sign-In panel user harus mendarat di
 * home panel user (`/user/home`), bukan dilempar ke panel admin.
 *
 * Yang diuji:
 *   - slug route auth panel user (`/user/signin`, `/user/signup`) dan panel
 *     admin (`/admin/signin`), plus alias URL lama (`/user/login`,
 *     `/user/register`, `/admin/login`) supaya bookmark lama tidak jadi 404,
 *   - panel root `/user` untuk super admin -> `/user/home`,
 *   - tombol "Masuk Dengan Google" (POST /auth/firebase/callback), yang
 *     sebelumnya mengembalikan `/admin` khusus untuk super admin.
 */

use App\Models\User\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create([
        'email' => 'superadmin@example.test',
        'username' => 'superadmin',
    ]);

    $this->superAdmin->assignRole('super_admin');

    $this->loginUrl = route('filament.user.auth.login');
    $this->homeUrl = route('filament.user.pages.home');
});

test('halaman sign-in panel user berada di /user/signin', function (): void {
    expect($this->loginUrl)->toContain('/user/signin');

    get('/user/signin')->assertOk();
});

test('URL lama /user/login dilayani redirect, bukan 404', function (): void {
    get('/user/login')->assertRedirect($this->loginUrl);
});

test('halaman sign-up panel user berada di /user/signup', function (): void {
    expect(route('filament.user.auth.register'))->toContain('/user/signup');

    get('/user/signup')->assertOk();
});

test('URL lama /user/register dilayani redirect, bukan 404', function (): void {
    get('/user/register')->assertRedirect(route('filament.user.auth.register'));
});

test('halaman sign-in panel admin berada di /admin/signin', function (): void {
    expect(route('filament.admin.auth.login'))->toContain('/admin/signin');

    get('/admin/signin')->assertOk();
});

test('URL lama /admin/login dilayani redirect, bukan 404', function (): void {
    get('/admin/login')->assertRedirect(route('filament.admin.auth.login'));
});

test('redirect /admin untuk tamu mengikuti slug sign-in yang baru', function (): void {
    // Kalau middleware Authenticate masih menunjuk ke slug lama, Filament
    // akan melempar tamu ke URL yang sudah tidak ada (404), bukan 302.
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

test('user non admin tetap tidak bisa masuk panel admin', function (): void {
    actingAs(User::factory()->create(), 'web')
        ->get('/admin')
        ->assertForbidden();
});

test('panel root /user mengarahkan super admin ke /user/home', function (): void {
    actingAs($this->superAdmin, 'web')
        ->get('/user')
        ->assertRedirect($this->homeUrl);
});

test('google sign in mengarahkan super admin ke /user/home', function (): void {
    Http::fake([
        'identitytoolkit.googleapis.com/*' => Http::response([
            'users' => [[
                'localId' => 'firebase-local-id-1',
                'email' => 'superadmin@example.test',
                'displayName' => 'Super Admin',
                'photoUrl' => 'https://example.test/avatar.png',
                'emailVerified' => true,
            ]],
        ]),
        '*' => Http::response('binary', 200),
    ]);

    postJson('/auth/firebase/callback', ['id_token' => 'fake-id-token'])
        ->assertOk()
        ->assertJsonPath('redirect', $this->homeUrl);
});
