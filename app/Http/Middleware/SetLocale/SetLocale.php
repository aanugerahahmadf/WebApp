<?php

namespace App\Http\Middleware\SetLocale;

use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // 1. Cek parameter request query/post 'locale' atau 'lang' terlebih dahulu
        if ($request->has('locale')) {
            $locale = (string) $request->input('locale');
        } elseif ($request->has('lang')) {
            $locale = (string) $request->input('lang');
        }

        // 2. Cek session jika session store aktif pada request ini
        if (! $locale && $request->hasSession()) {
            $sessionLocale = (string) session()->get('locale');
            if ($sessionLocale) {
                $locale = $sessionLocale;
            }
        }

        // 3. Cek header Accept-Language dari client (sangat penting untuk API client mobile)
        //    Diprioritaskan di atas preferensi DB user agar Flutter bisa mengirim bahasa yang dipilih
        $acceptLanguage = $request->header('Accept-Language');
        if (! $locale && $acceptLanguage) {
            $langs = explode(',', $acceptLanguage);
            $firstLang = trim($langs[0]);
            $cleanLang = str_replace('-', '_', $firstLang);

            $localsConfig = config('filament-language-switcher.locals', ['id' => [], 'en' => []]);
            $supported = array_keys($localsConfig);

            if (in_array($cleanLang, $supported)) {
                $locale = $cleanLang;
            } else {
                $shortLang = explode('_', $cleanLang)[0];
                foreach ($supported as $sup) {
                    if ($sup === $shortLang || explode('_', $sup)[0] === $shortLang) {
                        $locale = $sup;
                        break;
                    }
                }
            }
        }

        // 4. Cek autentikasi user di semua guards untuk mendapatkan preferensi bahasa dari database
        //    Hanya digunakan jika locale belum ditentukan dari param/session/header
        $user = null;
        $guards = ['web', 'filament', 'admin', 'mobile', 'nativephp', 'api', 'sanctum'];
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
            $isMobile = NativeServiceProvider::isNativeMobile();
            $dbLocale = null;

            if (! $isMobile) {
                try {
                    $dbLocale = $user->lang;
                } catch (\Throwable $e) {
                    $dbLocale = null;
                }
            }

            if ($locale && $locale !== $dbLocale && ! $isMobile) {
                try {
                    UserLanguage::updateOrCreate(
                        ['model_id' => (string) $user->id, 'model_type' => get_class($user)],
                        ['lang' => $locale]
                    );
                    if (method_exists($user, 'setRawAttributes')) {
                        $user->setRawAttributes(['lang' => $locale], true);
                    }
                } catch (\Exception $e) {
                }
            } elseif ($dbLocale && ! $locale) {
                $locale = (string) $dbLocale;
            }
        }

        // 5. Fallback Default jika belum terdeteksi
        if (! $locale) {
            if (NativeServiceProvider::isNativeMobile()) {
                $locale = 'id';
            } else {
                $localsConfig = config('filament-language-switcher.locals', ['id' => [], 'en' => []]);
                $supported = array_keys($localsConfig);
                $locale = $request->getPreferredLanguage($supported ?: ['id', 'en']) ?: 'id';
            }
        }

        // 6. Terapkan locale ke sistem
        if ($locale) {
            app()->setLocale($locale);
            config(['app.locale' => $locale]);

            if ($request->hasSession()) {
                session()->put('locale', (string) $locale);
            }

            if (class_exists(Filament::class)) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
