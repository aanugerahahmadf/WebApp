<?php

namespace App\Http\Middleware\SetLocale;

use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use App\Support\AppPlatform\AppPlatform;
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
        //
        // NILAI HARUS DISARING lebih dulu. Branch 3 (Accept-Language) sudah
        // memvalidasi terhadap daftar locale yang didukung, tapi branch 1 tidak.
        // Sebelumnya `?locale=<apa saja>` langsung dipakai apa adanya --
        // termasuk nilai yang tidak ada di switcher -- lalu nilai itu ikut
        // tersimpan di `user_languages`. Akibatnya preferensi yang tersimpan tidak
        // punya berkas terjemahan, dan setiap pembacaan berikutnya jatuh ke
        // default Laravel. Query string adalah input pengguna, jadi harus
        // diperlakukan sebagai input, bukan dipercaya.
        $supportedLocales = array_keys(config('filament-language-switcher.locals', ['id' => [], 'en' => []]));

        if ($request->has('locale')) {
            $requested = (string) $request->input('locale');
        } elseif ($request->has('lang')) {
            $requested = (string) $request->input('lang');
        } else {
            $requested = null;
        }

        if ($requested !== null && in_array($requested, $supportedLocales, strict: true)) {
            $locale = $requested;
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
        $guards = ['web', 'filament', 'admin', 'mobile', 'api', 'sanctum'];
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
            $dbLocale = null;

            try {
                $dbLocale = $user->lang;
            } catch (\Throwable $e) {
                $dbLocale = null;
            }

            if ($locale && $locale !== $dbLocale) {
                try {
                    // WAJIB `getMorphClass()`, bukan `get_class($user)`.
                    //
                    // User memakai trait `LegacyMorphClass`, jadi morph class-nya
                    // adalah `App\Models\User` -- bukan FQCN sebenarnya
                    // (`App\Models\User\User`). Relasi `InteractsWithLanguages::lang()`
                    // mencari dengan `getMorphClass()`, jadi kalau penulisan memakai
                    // `get_class()` barisnya tersimpan dengan nilai yang berbeda dan
                    // TIDAK PERNAH ditemukan lagi: pilihan bahasa pengguna hilang
                    // diam-diam, dan accessor `lang()` selalu mengembalikan default.
                    // Ditulis dengan `getMorphClass()` supaya baca dan tulis memakai
                    // kunci yang sama, persis seperti relasi morph yang lain.
                    UserLanguage::updateOrCreate(
                        [
                            'model_id' => (string) $user->id,
                            'model_type' => $user->getMorphClass(),
                        ],
                        ['lang' => $locale]
                    );
                    if (method_exists($user, 'unsetRelation')) {
                        $user->unsetRelation('lang');
                    }
                } catch (\Exception $e) {
                }
            } elseif ($dbLocale && ! $locale) {
                $locale = (string) $dbLocale;
            }
        }

        // 5. Fallback Default jika belum terdeteksi
        if (! $locale) {
            if (AppPlatform::isNativeMobile()) {
                // The Capacitor shells ship an Indonesian-first bundle; don't
                // let a system language other than the app default take over.
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
