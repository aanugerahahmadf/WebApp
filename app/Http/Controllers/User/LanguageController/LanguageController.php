<?php

namespace App\Http\Controllers\User\LanguageController;

use App\Http\Controllers\Controller;
use App\Models\UserLanguage\UserLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{
    public function update(Request $request, string $locale): JsonResponse
    {
        $locals = config('filament-language-switcher.locals', []);

        if (! array_key_exists($locale, $locals)) {
            return response()->json(['message' => __('Bahasa tidak didukung.')], 422);
        }

        $this->persistLocale($request, $locale);

        return response()->json(['locale' => $locale]);
    }

    public function switch(Request $request, string $locale): RedirectResponse
    {
        $this->persistLocale($request, $locale);

        return back()->with('switched_locale', $locale);
    }

    private function persistLocale(Request $request, string $locale): void
    {
        $locals = config('filament-language-switcher.locals', []);

        if (! array_key_exists($locale, $locals)) {
            return;
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);
        config(['app.locale' => $locale]);

        $user = null;
        $guards = ['web', 'filament', 'admin', 'mobile', 'api'];
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

        if ($user) {
            try {
                UserLanguage::updateOrCreate(
                    ['model_id' => (string) $user->id, 'model_type' => get_class($user)],
                    ['lang' => $locale]
                );

                cache()->forget("user_lang_{$user->id}");
                cache()->forget("active_trans_map_{$locale}");
            } catch (\Exception $e) {
            }
        }
    }
}
