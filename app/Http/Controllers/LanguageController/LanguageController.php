<?php

namespace App\Http\Controllers\LanguageController;

use App\Http\Controllers\Controller;

use App\Models\UserLanguage\UserLanguage;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Persist a locale without navigating away from the current page.
     * Used by the header switcher so unverified users are not redirected to
     * the email-verification prompt while choosing a language.
     */
    public function update(Request $request, string $locale): JsonResponse
    {
        $locals = config('filament-language-switcher.locals', []);

        if (! array_key_exists($locale, $locals)) {
            return response()->json(['message' => __('Bahasa tidak didukung.')], 422);
        }

        $this->switch($request, $locale);

        return response()->json(['locale' => $locale]);
    }

    /**
     * Switch the application language.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $locals = config('filament-language-switcher.locals', []);

        if (array_key_exists($locale, $locals)) {
            // 1. Core State Update
            session()->put('locale', $locale);
            app()->setLocale($locale);
            config(['app.locale' => $locale]);

            // 2. Multi-Guard Authentication Check
            $user = null;
            $guards = ['web', 'filament', 'admin', 'mobile', 'nativephp', 'api'];
            foreach ($guards as $guard) {
                try {
                    $guardInstance = Auth::guard($guard);
                    if ($guardInstance->check()) {
                        $user = $guardInstance->user();
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // 3. Database Persistence — skip on NativePHP mobile to avoid proxy errors
            $isMobile = NativeServiceProvider::isNativeMobile();

            if ($user && ! $isMobile) {
                try {
                    UserLanguage::updateOrCreate(
                        ['model_id' => (string) $user->id, 'model_type' => get_class($user)],
                        ['lang' => $locale]
                    );

                    // Purge caches to ensure the new locale is used in next request
                    cache()->forget("user_lang_{$user->id}");
                    cache()->forget("active_trans_map_{$locale}");
                } catch (\Exception $e) {
                    // Fail silently
                }
            } elseif ($user && $isMobile) {
                // On mobile: only clear cache, skip DB write
                try {
                    cache()->forget("user_lang_{$user->id}");
                    cache()->forget("active_trans_map_{$locale}");
                } catch (\Exception $e) {
                    // Fail silently
                }
            }
        }

        // Final safety redirect back with session confirmation
        return back()->with('switched_locale', $locale);
    }
}
