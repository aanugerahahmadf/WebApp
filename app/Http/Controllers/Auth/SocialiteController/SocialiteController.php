<?php

namespace App\Http\Controllers\Auth\SocialiteController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class SocialiteController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // REDIRECT — Mulai alur OAuth
    // ─────────────────────────────────────────────────────────────────────

    public function redirect(string $provider, Request $request)
    {
        $isMobileApp = AppPlatform::isMobileApp();

        Log::info("[Socialite] Redirect to $provider | mobile_app=$isMobileApp");

        // A Capacitor shell is a WebView pointing at this same server, so it
        // shares the origin — and therefore the session cookie jar. The only
        // difference from a desktop browser is that Google refuses to render its
        // consent screen inside an embedded user agent, so the auth URL is
        // handed back to the shell to open in a Custom Tab / system browser
        // rather than navigated to in-place.
        if ($isMobileApp) {
            return $this->redirectMobileApp($provider, $request);
        }

        config(["services.$provider.redirect" => route('auth.callback', $provider)]);

        return Socialite::driver($provider)
            ->scopes([
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/user.birthday.read',
                'https://www.googleapis.com/auth/user.gender.read',
                'https://www.googleapis.com/auth/user.phonenumbers.read',
                'https://www.googleapis.com/auth/user.addresses.read',
            ])
            ->redirect();
    }

    // ─────────────────────────────────────────────────────────────────────
    // MOBILE APP REDIRECT
    // Google hanya boleh dibuka di Custom Tab / browser sistem, bukan di dalam
    // WebView. Server tidak bisa memaksa itu, jadi URL-nya dikembalikan ke shell
    // (JSON saat dipanggil via fetch, atau redirect biasa sebagai fallback) dan
    // shell yang membukanya. Callback tetap mendarat di server ini.
    // ─────────────────────────────────────────────────────────────────────

    private function redirectMobileApp(string $provider, Request $request)
    {
        $callbackUrl = route('auth.callback', $provider);
        config(["services.$provider.redirect" => $callbackUrl]);

        $authUrl = Socialite::driver($provider)
            ->stateless()
            ->scopes([
                'openid',
                'profile',
                'email',
            ])
            ->redirect()
            ->getTargetUrl();

        Log::info("[Socialite Mobile App] Auth URL: $authUrl");
        Log::info("[Socialite Mobile App] Callback URL: $callbackUrl");

        // Dipanggil via fetch (AJAX) — shell akan buka URL ini di Custom Tab.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'url' => $authUrl,
            ]);
        }

        // Fallback: navigasi biasa. Berfungsi di Custom Tab dan di browser.
        return redirect($authUrl);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CALLBACK WEB — Alur standar untuk web browser
    // ─────────────────────────────────────────────────────────────────────

    public function callback(string $provider)
    {
        try {
            config(["services.$provider.redirect" => route('auth.callback', $provider)]);
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error("[Socialite] Web callback error: {$e->getMessage()}");

            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Gagal mengambil data dari :provider.', ['provider' => ucfirst($provider)]));
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        if (! $user) {
            return redirect()->route('filament.user.auth.login');
        }

        if (is_null($user->email_verified_at)) {
            Auth::login($user, remember: true);

            return redirect()->route('filament.user.auth.email-verification.prompt');
        }

        Auth::login($user, remember: true);

        return $this->redirectAfterLogin($user);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LEGACY CALLBACK ROUTES — kept so stale redirect URIs don't 404
    //
    // These existed only for NativePHP: the app declared a reverse client-id
    // scheme, stashed the user id in a short-lived cache token, then bounced
    // the browser to weddingapp:// so the native layer could translate it back
    // into a WebView load. A Capacitor shell has no such translation step — its
    // WebView loads this server directly, so the callback response already
    // carries the session cookie. Both routes therefore just run the ordinary
    // session-based callback().
    // ─────────────────────────────────────────────────────────────────────

    public function callbackMobileScheme(string $provider, Request $request)
    {
        Log::info('[Socialite] Legacy scheme callback received', $request->only(['code', 'error']));

        return $this->callback($provider, $request);
    }

    public function callbackMobile(string $provider, Request $request)
    {
        Log::info('[Socialite] Legacy mobile callback received', $request->only(['code', 'error']));

        return $this->callback($provider, $request);
    }

    // ─────────────────────────────────────────────────────────────────────
    // TOKEN HANDOFF — Route: /auth/mobile/verify?token=xxx
    //
    // No longer part of the OAuth path (see above), but still honoured: when a
    // valid token is presented, log that user in.
    // ─────────────────────────────────────────────────────────────────────

    public function verifyMobileToken(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            Log::warning('[Socialite Mobile] verifyMobileToken: no token');

            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Token tidak valid.'));
        }

        $userId = Cache::pull("mobile_auth_token_{$token}");

        if (! $userId) {
            Log::warning("[Socialite Mobile] verifyMobileToken: token expired or invalid: $token");

            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Token sudah kadaluarsa. Silakan coba lagi.'));
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Akun tidak ditemukan.'));
        }

        Auth::login($user, remember: true);

        Log::info("[Socialite Mobile] User {$user->id} logged in via mobile token");

        return $this->redirectAfterLogin($user);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function findOrCreateUser($socialUser, string $provider): ?User
    {
        // Cari berdasarkan social_id atau email
        $user = User::query()
            ->where('social_id', $socialUser->getId())
            ->where('social_type', $provider)
            ->first()
            ?? User::query()->where('email', $socialUser->getEmail())->first();

        if ($user) {
            $rawUser = $socialUser->getRaw();
            Log::info("[Socialite Debug] Raw Google User Data for {$user->email}: ".json_encode($rawUser));

            // Selalu update Info Sosial (Nama & Foto) agar sinkron dengan Google terbaru
            $fullName = $socialUser->getName() ?? $user->full_name;
            $updates['full_name'] = $fullName;

            // Download foto Google ke lokal agar sinkron di kotak upload profil
            if ($socialUser->getAvatar()) {
                try {
                    $avatarContents = Http::get($socialUser->getAvatar())->body();
                    $filename = 'avatars/'.$user->id.'_'.time().'.jpg';
                    Storage::disk('public')->put($filename, $avatarContents);
                    $updates['avatar_url'] = $filename;
                } catch (\Exception $e) {
                    $updates['avatar_url'] = $socialUser->getAvatar();
                }
            }

            $updates['social_id'] = $socialUser->getId();
            $updates['social_type'] = $provider;

            // Pecah nama menjadi detail (Depan, Tengah, Belakang)
            $parts = explode(' ', trim($fullName));
            $updates['first_name'] = array_shift($parts);
            $updates['last_name'] = count($parts) > 0 ? array_pop($parts) : '';
            $updates['mid_name'] = count($parts) > 0 ? implode(' ', $parts) : '';

            // Ambil data tambahan jika ada (membutuhkan scope tambahan)
            $rawUser = $socialUser->getRaw();
            if (isset($rawUser['birthdays'][0]['date'])) {
                $d = $rawUser['birthdays'][0]['date'];
                // Mapping ke wedding_date atau kolom lain jika ada
                $user->wedding_date = "{$d['year']}-{$d['month']}-{$d['day']}";
            }

            if (isset($rawUser['genders'][0]['value'])) {
                // Google return: male, female, unspecified
                $updates['gender'] = $rawUser['genders'][0]['value'];
            }

            if (isset($rawUser['phoneNumbers'][0]['value'])) {
                $updates['phone'] = $rawUser['phoneNumbers'][0]['value'];
            }

            if (isset($rawUser['addresses'][0]['formattedValue'])) {
                $updates['address'] = $rawUser['addresses'][0]['formattedValue'];
            }

            $user->update($updates);

            return $user;
        }

        // Buat user baru
        $username = $socialUser->getNickname() ?? explode('@', $socialUser->getEmail())[0];
        $base = $username;
        $i = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base.$i++;
        }

        // User tidak ditemukan, buat akun baru
        $rawUser = $socialUser->getRaw();
        Log::info('[Socialite Debug] Creating New User from Google: '.json_encode($rawUser));

        try {
            $fullName = $socialUser->getName() ?? $username;
            $parts = explode(' ', trim($fullName));
            $firstName = array_shift($parts);
            $lastName = count($parts) > 0 ? array_pop($parts) : '';
            $midName = count($parts) > 0 ? implode(' ', $parts) : '';

            $rawUser = $socialUser->getRaw();
            $gender = isset($rawUser['genders'][0]['value']) ? $rawUser['genders'][0]['value'] : null;

            $user = User::create([
                'full_name' => $fullName,
                'first_name' => $firstName,
                'mid_name' => $midName,
                'last_name' => $lastName,
                'username' => $username,
                'email' => $socialUser->getEmail(),
                'social_id' => $socialUser->getId(),
                'social_type' => $provider,
                'avatar_url' => (function () use ($socialUser) {
                    try {
                        $avatarContents = Http::get($socialUser->getAvatar())->body();
                        $filename = 'avatars/new_'.time().'_'.Str::random(5).'.jpg';
                        Storage::disk('public')->put($filename, $avatarContents);

                        return $filename;
                    } catch (\Exception $e) {
                        return $socialUser->getAvatar();
                    }
                })(),
                'gender' => $gender,
                'phone' => isset($rawUser['phoneNumbers'][0]['value']) ? $rawUser['phoneNumbers'][0]['value'] : null,
                'address' => isset($rawUser['addresses'][0]['formattedValue']) ? $rawUser['addresses'][0]['formattedValue'] : null,
                'email_verified_at' => null,
                'active_status' => true,
                'ip_address' => request()->ip(),
                'password' => null,
            ]);

            if (method_exists($user, 'assignRole')) {
                $role = Role::where('name', 'customer')->first()
                    ?? Role::where('name', 'user')->first();
                if ($role) {
                    $user->assignRole($role);
                }
            }

            return $user;
        } catch (\Exception $e) {
            Log::error("[Socialite] User creation failed: {$e->getMessage()}");

            return null;
        }
    }

    private function redirectAfterLogin(User $user)
    {
        if ($user->hasRole('super_admin')) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended('/user');
    }
}
