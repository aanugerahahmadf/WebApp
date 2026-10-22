<?php

namespace App\Http\Middleware\SetLocale;

use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use App\Support\AppLocale\AppLocale;
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
        // 1-3. Parameter `?locale=`/`?lang=`, session, header Accept-Language.
        //
        // Ketiganya dibaca dari `App\Support\AppLocale\AppLocale` -- satu
        // sumber aturan yang juga dipakai halaman error. Halaman error
        // TIDAK selalu melewati middleware ini (404 untuk URL yang tidak
        // dikenal dilempar router sebelum middleware route jalan), jadi
        // kalau aturannya ditulis dua kali, keduanya pasti akan berbeda
        // pada kasus yang paling jarang diuji.
        //
        // Perilaku dan urutannya tidak berubah: query > session >
        // Accept-Language.
        $locale = AppLocale::detected($request);

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
            $locale = AppLocale::fallback($request);
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
