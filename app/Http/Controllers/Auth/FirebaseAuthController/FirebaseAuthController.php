<?php

namespace App\Http\Controllers\Auth\FirebaseAuthController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Firebase Auth login — replaces Google Socialite.
 *
 * The client (web browser or NativePHP WebView) signs in with
 * Firebase Auth and sends the resulting ID token here. This controller
 * verifies the token via the Firebase Auth REST API (accounts:lookup)
 * without requiring a service account, then find-or-creates the local
 * user and logs them in.
 */
class FirebaseAuthController extends Controller
{
    /**
     * Verify the Firebase ID token and log the user in.
     */
    public function callback(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $apiKey = config('firebase.api_key');

        if (! $apiKey) {
            Log::error('[FirebaseAuth] FIREBASE_API_KEY is not configured');

            return response()->json(['message' => __('Konfigurasi Firebase tidak lengkap.')], 500);
        }

        $profile = $this->verifyIdToken($request->input('id_token'), $apiKey);

        if (! $profile) {
            return response()->json(['message' => __('Token Firebase tidak valid.')], 401);
        }

        $user = $this->findOrCreateUser($profile);

        if (! $user) {
            return response()->json(['message' => __('Gagal membuat akun.')], 500);
        }

        Auth::login($user, remember: true);

        Log::info("[FirebaseAuth] User {$user->id} ({$user->email}) logged in | new={$user->wasRecentlyCreated}");

        // User baru Google → OTP → CompleteProfile → Home
        if ($user->wasRecentlyCreated || is_null($user->email_verified_at)) {
            return response()->json([
                'redirect' => route('filament.user.auth.email-verification.prompt'),
            ]);
        }

        // User sudah terdaftar → langsung Home
        return response()->json([
            'redirect' => $user->hasRole('super_admin') ? '/admin' : '/user',
        ]);
    }

    /**
     * Verify an ID token against Firebase Auth REST API.
     *
     * @return array<string, mixed>|null Firebase user profile, or null on failure
     */
    private function verifyIdToken(string $idToken, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.$apiKey, [
                    'idToken' => $idToken,
                ]);
        } catch (\Throwable $e) {
            Log::error("[FirebaseAuth] accounts:lookup network error: {$e->getMessage()}");

            return null;
        }

        if ($response->failed()) {
            Log::warning('[FirebaseAuth] accounts:lookup failed: '.$response->body());

            return null;
        }

        $users = $response->json('users');

        if (empty($users) || ! isset($users[0])) {
            Log::warning('[FirebaseAuth] accounts:lookup returned no users');

            return null;
        }

        return $users[0];
    }

    /**
     * Find the local user by Firebase data, or create one.
     */
    private function findOrCreateUser(array $profile): ?User
    {
        $localId = $profile['localId'] ?? null;
        $email = $profile['email'] ?? null;
        $fullName = $profile['displayName'] ?? null;
        $photoUrl = $profile['photoUrl'] ?? null;
        $emailVerified = (bool) ($profile['emailVerified'] ?? false);

        if (! $email && ! $localId) {
            Log::warning('[FirebaseAuth] profile missing email and localId', $profile);

            return null;
        }

        $user = User::query()
            ->where('social_id', $localId)
            ->where('social_type', 'firebase')
            ->first()
            ?? ($email ? User::query()->where('email', $email)->first() : null);

        if ($user) {
            $updates = [
                'social_id' => $localId,
                'social_type' => 'firebase',
            ];

            if ($fullName) {
                $updates['full_name'] = $fullName;
                $parts = explode(' ', trim($fullName));
                $updates['first_name'] = array_shift($parts);
                $updates['last_name'] = count($parts) > 0 ? array_pop($parts) : '';
                $updates['mid_name'] = count($parts) > 0 ? implode(' ', $parts) : '';
            }

            if ($photoUrl) {
                $updates['avatar_url'] = $this->downloadAvatar($photoUrl, $user->id);
            }

            if ($emailVerified && is_null($user->email_verified_at)) {
                $updates['email_verified_at'] = now();
            }

            $user->update($updates);

            return $user;
        }

        $username = $email ? explode('@', $email)[0] : 'user_'.$localId;
        $base = $username;
        $i = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base.$i++;
        }

        $parts = $fullName ? explode(' ', trim($fullName)) : [];
        $firstName = $parts ? array_shift($parts) : $username;
        $lastName = count($parts) > 0 ? array_pop($parts) : '';
        $midName = count($parts) > 0 ? implode(' ', $parts) : '';

        try {
            $user = User::create([
                'full_name' => $fullName ?? $username,
                'first_name' => $firstName,
                'mid_name' => $midName,
                'last_name' => $lastName,
                'username' => $username,
                'email' => $email,
                'social_id' => $localId,
                'social_type' => 'firebase',
                'avatar_url' => $photoUrl ? $this->downloadAvatar($photoUrl, null) : null,
                'email_verified_at' => $emailVerified ? now() : null,
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
            Log::error("[FirebaseAuth] User creation failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Download the Firebase/Gmail avatar to local storage.
     *
     * @return string|null Stored avatar path, or the original URL on failure
     */
    private function downloadAvatar(string $url, ?int $userId): ?string
    {
        try {
            $contents = Http::timeout(10)->get($url)->body();
            $name = 'avatars/'.($userId ?? 'new').'_'.time().'_'.Str::random(5).'.jpg';
            Storage::disk('public')->put($name, $contents);

            return $name;
        } catch (\Throwable $e) {
            return $url;
        }
    }
}
