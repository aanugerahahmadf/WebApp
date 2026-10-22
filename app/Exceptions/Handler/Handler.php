<?php

namespace App\Exceptions\Handler;

use App\Support\AppLocale\AppLocale;
use App\Support\ErrorPage\ErrorPage;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            //
        });
    }

    /**
     * Get the view used to render HTTP exceptions.
     *
     * SEBELUMNYA: selalu `User.errors.{status}.{status}`. Satu folder untuk
     * semua panel -- jadi request di /admin/* dan request di storefront
     * memakai view yang sama persis.
     *
     * SEKARANG: panel aktif didahulukan. Request di /admin/* memakai
     * `Admin.errors.*`, sisanya `Welcome.errors.*`. Alasannya bukan tampilan
     * saja: tombol "Kembali ke" harus mengarah ke panel yang benar, dan
     *warnanya harus sesuai primary panel itu (indigo untuk admin, emas
     * untuk storefront).
     *
     * Both lookups are safe: `ErrorPage::viewCandidates()` mengembalikan
     * beberapa kandidat berurutan, dan view yang dipakai adalah yang
     * pertama BENAR-BENAR ADA. Kalau satu set view dihapus, halaman error
     * tidak ikut hilang -- ia jatuh ke set berikutnya, lalu ke bawaan
     * Laravel (`errors::minimal`, yang sekarang juga glass).
     */
    protected function getHttpExceptionView(HttpExceptionInterface $e)
    {
        $status = $e->getStatusCode();

        // Bahasa halaman error harus benar SEBELUM view dirender.
        //
        // 404 untuk URL yang tidak dikenal dilempar oleh ROUTER, sebelum
        // middleware route (termasuk grup `web`) sempat jalan -- jadi
        // `SetLocale` tidak pernah dipanggil. Tanpa langkah ini, halaman
        // error selalu memakai default aplikasi, bukan bahasa yang diminta
        // browser: orang berbahasa Inggris melihat "Tidak ditemukan".
        //
        // `AppLocale` tidak menyentuh database (halaman error boleh muncul
        // justru saat database tidak terjangkau) dan mengikuti urutan yang
        // sama dengan `SetLocale`: query > session > Accept-Language.
        AppLocale::apply(request());

        foreach (ErrorPage::viewCandidates($status) as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        return parent::getHttpExceptionView($e);
    }
}
