<?php

namespace App\Http\Controllers\Auth\SocialiteController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Native\Mobile\Browser;
use Native\Mobile\Notification as NativeNotification;
use Spatie\Permission\Models\Role;

class SocialiteController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // REDIRECT — Mulai alur OAuth
    // ─────────────────────────────────────────────────────────────────────

    public function redirect(string $provider, Request $request)
    {
        $isMobile = NativeServiceProvider::isNativeMobile()
            || $request->boolean('native')
            || $request->header('X-NativePHP') === '1';

        Log::info("[Socialite] Redirect to $provider | mobile=$isMobile");

        if ($isMobile) {
            return $this->redirectMobile($provider, $request);
        }

        // Web: alur standar Socialite
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
    // MOBILE REDIRECT — Buka Google OAuth via Browser::auth()
    // Menggunakan reverse client ID scheme agar tidak perlu server publik.
    // Google redirect ke: com.googleusercontent.apps.CLIENT_ID:/oauth2redirect
    // NativePHP tangkap deep link ini dan load /auth/mobile/google/callback
    // ─────────────────────────────────────────────────────────────────────

    private function redirectMobile(string $provider, Request $request)
    {
        $clientId = config("services.{$provider}.client_id");

        // Reverse client ID scheme — tidak perlu server publik, Google redirect
        // langsung ke app Android via custom URI scheme.
        // Format: com.googleusercontent.apps.CLIENT_ID:/oauth2redirect
        $reverseClientId = 'com.googleusercontent.apps.'.str_replace('.apps.googleusercontent.com', '', $clientId);
        $callbackUrl = $reverseClientId.':/oauth2redirect';

        config(["services.$provider.redirect" => $callbackUrl]);

        // Dapatkan URL OAuth Google
        $authUrl = Socialite::driver($provider)
            ->stateless()
            ->with(['redirect_uri' => $callbackUrl])
            ->scopes([
                'openid',
                'profile',
                'email',
            ])
            ->redirect()
            ->getTargetUrl();

        Log::info("[Socialite Mobile] Auth URL: $authUrl");
        Log::info("[Socialite Mobile] Callback URL: $callbackUrl");

        // Buka di in-app browser (Custom Tabs Android / SFSafariViewController iOS)
        $opened = false;
        if (class_exists(Browser::class) && function_exists('nativephp_call')) {
            $browser = new Browser;
            $opened = $browser->auth($authUrl);
            Log::info('[Socialite Mobile] Browser::auth opened: '.($opened ? 'yes' : 'no'));
        }

        // Jika dipanggil via fetch (AJAX), return JSON
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'opened' => $opened,
                'url' => $authUrl,
            ]);
        }

        // Fallback: redirect biasa
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
    // CALLBACK MOBILE via Reverse Client ID Scheme
    // NativePHP intercept deep link: com.googleusercontent.apps.xxx:/oauth2redirect
    // dan load route ini dengan query params dari Google
    // ─────────────────────────────────────────────────────────────────────

    public function callbackMobileScheme(string $provider, Request $request)
    {
        Log::info('[Socialite Mobile Scheme] Callback received', $request->all());

        $code = $request->query('code');

        if (! $code) {
            Log::error('[Socialite Mobile Scheme] No code in callback');

            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Gagal login dengan Google. Tidak ada kode otorisasi.'));
        }

        try {
            $clientId = config("services.{$provider}.client_id");
            $reverseId = 'com.googleusercontent.apps.'.str_replace('.apps.googleusercontent.com', '', $clientId);
            $callbackUrl = $reverseId.':/oauth2redirect';

            config(["services.$provider.redirect" => $callbackUrl]);

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->with(['redirect_uri' => $callbackUrl])
                ->userFromCode($code);

        } catch (\Exception $e) {
            Log::error("[Socialite Mobile Scheme] Error: {$e->getMessage()}");

            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Gagal mengambil data dari Google.'));
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        if (! $user) {
            return redirect()->route('filament.user.auth.login')
                ->with('error', __('Gagal membuat akun.'));
        }

        if (is_null($user->email_verified_at)) {
            Auth::login($user, remember: true);

            return redirect()->route('filament.user.auth.email-verification.prompt');
        }

        Auth::login($user, remember: true);

        Log::info("[Socialite Mobile Scheme] User {$user->id} logged in");

        if (NativeServiceProvider::isNativeMobile()) {
            try {
                NativeNotification::new()
                    ->title(__('Berhasil Masuk!'))
                    ->message(__('Halo :name, selamat datang kembali.', ['name' => $user->first_name ?? $user->full_name]))
                    ->show();
            } catch (\Throwable) {
            }
        }

        return $this->redirectAfterLogin($user);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CALLBACK MOBILE — Dipanggil setelah Google redirect ke server
    // Server simpan token sementara, lalu redirect ke deep link
    // ─────────────────────────────────────────────────────────────────────

    public function callbackMobile(string $provider, Request $request)
    {
        try {
            $callbackUrl = route('auth.callback.mobile', $provider);
            config(["services.$provider.redirect" => $callbackUrl]);

            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            Log::error("[Socialite Mobile] Callback error: {$e->getMessage()}");

            // Redirect ke deep link dengan error
            $scheme = config('nativephp.deeplink_scheme', 'weddingapp');

            return redirect("{$scheme}://auth/error?message=".urlencode(__('Gagal login dengan Google.')));
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        if (! $user) {
            $scheme = config('nativephp.deeplink_scheme', 'weddingapp');

            return redirect("{$scheme}://auth/error?message=".urlencode(__('Gagal membuat akun.')));
        }

        // Simpan token sementara di cache (60 detik) — mobile app akan ambil ini
        $token = Str::random(64);
        Cache::put("mobile_auth_token_{$token}", $user->id, now()->addMinutes(2));

        Log::info("[Socialite Mobile] Token created for user {$user->id}: $token");

        // Redirect ke deep link — NativePHP akan tangkap ini dan load di WebView
        // Format: weddingapp://auth/google/success?token=xxx
        // NativePHP akan translate ini ke: http://localhost/auth/google/success?token=xxx
        $scheme = config('nativephp.deeplink_scheme', 'weddingapp');

        // Juga simpan token di session sebagai fallback
        session(['mobile_auth_pending_token' => $token]);

        return redirect("{$scheme}://auth/google/success?token={$token}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // DEEP LINK HANDLER — Dipanggil saat app menerima deep link callback
    // Route: /auth/mobile/verify?token=xxx
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

        // Notifikasi Native jika di mobile
        if (app()->environment('mobile') || NativeServiceProvider::isNativeMobile()) {
            NativeNotification::new()
                ->title(__('Berhasil Masuk!'))
                ->message(__('Halo :name, selamat datang kembali.', ['name' => $user->first_name ?? $user->full_name]))
                ->show();
        }

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
