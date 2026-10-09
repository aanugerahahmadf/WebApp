<?php

use App\Http\Controllers\Admin\ConsultationFormPdfController\ConsultationFormPdfController as AdminConsultationFormPdfController;
use App\Http\Controllers\Admin\DataExportDownloadController\DataExportDownloadController;
use App\Http\Controllers\Admin\InvoicePdfController\InvoicePdfController as AdminInvoicePdfController;
use App\Http\Controllers\Admin\ReportPdfController\ReportPdfController as AdminReportPdfController;
use App\Http\Controllers\Admin\ReviewVoteController\ReviewVoteController as AdminReviewVoteController;
use App\Http\Controllers\CookieConsent\CookieConsentController;
use App\Http\Controllers\User\ClerkLoginController\ClerkLoginController;
use App\Http\Controllers\User\ConsultationFormPdfController\ConsultationFormPdfController;
use App\Http\Controllers\User\FirebaseAuthController\FirebaseAuthController;
use App\Http\Controllers\User\InvoicePdfController\InvoicePdfController;
use App\Http\Controllers\User\LanguageController\LanguageController;
use App\Http\Controllers\User\MediaController\MediaController;
use App\Http\Controllers\User\ReportPdfController\ReportPdfController;
use App\Http\Controllers\User\ReviewReportController\ReviewReportController;
use App\Http\Controllers\User\ReviewVoteController\ReviewVoteController;
use App\Http\Controllers\User\SocialiteController\SocialiteController;
use App\Http\Controllers\Welcome\ConsultationFormPdfController\ConsultationFormPdfController as WelcomeConsultationFormPdfController;
use App\Http\Controllers\Welcome\InvoicePdfController\InvoicePdfController as WelcomeInvoicePdfController;
use App\Http\Controllers\Welcome\LegalWebController\LegalWebController as WelcomeLegalWebController;
use App\Http\Controllers\Welcome\ReportPdfController\ReportPdfController as WelcomeReportPdfController;
use App\Http\Controllers\Welcome\ReviewReportController\ReviewReportController as WelcomeReviewReportController;
use App\Http\Controllers\Welcome\ReviewVoteController\ReviewVoteController as WelcomeReviewVoteController;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\SuperAdmin\SuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — lengkap per scope: Shared / Welcome / User / Admin
|--------------------------------------------------------------------------
|
| Struktur controller web mengikuti pola API:
|
|   app/Http/Controllers/User/<Name>/<Name>.php       → namespace App\Http\Controllers\User\<Name>
|   app/Http/Controllers/Admin/<Name>/<Name>.php      → namespace App\Http\Controllers\Admin\<Name>
|   app/Http/Controllers/Welcome/<Name>/<Name>.php    → namespace App\Http\Controllers\Welcome\<Name>
|
|   - User/*    : implementasi kanonis (logika penuh).
|   - Admin/*   : extends User/* (perilaku identik, scope admin).
|   - Welcome/* : extends User/* (Legal memakai view Welcome.* dengan fallback User.*).
|
| Namespace lama sudah dihapus total (tidak ada alias):
|
|   App\Http\Controllers\Auth\SocialiteController + FirebaseAuthController
|     — diganti User/SocialiteController & User/FirebaseAuthController
|   App\Http\Controllers\LanguageController — diganti User/Admin/Welcome LanguageController
|   App\Http\Controllers\LegalWebController — diganti User/Admin/Welcome LegalWebController
|   App\Http\Controllers\PusherAuthController — diganti User/Admin/Welcome PusherAuthController
|
| Semua URI & nama route lama dipertahankan 100% agar tidak breaking.
|
*/

// -----------------------------------------------------------------------------
// SHARED / WELCOME — halaman publik (storefront)
// -----------------------------------------------------------------------------
// Legal Pages — HTML untuk mobile browser / HP fisik (scope Welcome).
Route::get('/legal/terms', [WelcomeLegalWebController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [WelcomeLegalWebController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/help', [WelcomeLegalWebController::class, 'help'])->name('legal.help');

// Alias ber-prefix /welcome untuk scope Welcome (tambahan, tidak menghapus yang lama).
Route::get('/welcome/legal/terms', [WelcomeLegalWebController::class, 'terms'])->name('welcome.legal.terms');
Route::get('/welcome/legal/privacy', [WelcomeLegalWebController::class, 'privacy'])->name('welcome.legal.privacy');
Route::get('/welcome/legal/help', [WelcomeLegalWebController::class, 'help'])->name('welcome.legal.help');

// The storefront is the 'welcome' Filament panel, and '/' is its front door.
// The old marketing page that used to live here has been removed: guests browse
// the landing page and the catalog, and anything account-shaped sends them to
// the user panel's auth landing page (see AuthenticateWelcome).
// '/' sadar-auth: semua yang login (termasuk super_admin, yang bisa
// memakai Admin Panel maupun User Panel) -> home akun user;
// tamu -> storefront welcome.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/user/home');
    }

    return redirect('/welcome/home');
})->middleware(SetLocale::class);

Route::redirect('/admin/inbox', '/admin/inbox/messages');

// -----------------------------------------------------------------------------
// SHARED — alias URL lama halaman auth
// Slug route auth berubah mengikuti penamaan class: `login` -> `signin`
// (loginRouteSlug di UserPanelProvider & AdminPanelProvider). URL utamanya
// sekarang `/user/signin`, `/admin/signin`. Bookmark & tautan lama tetap
// dilayani redirect supaya tidak jadi 404. Nama route tidak berubah, jadi
// route()/Filament::getLoginUrl() aman.
// -----------------------------------------------------------------------------
Route::redirect('/user/login', '/user/signin');
// Registrasi email/password DINONAKTIFKAN: `->registration()` tidak
// dipanggil di UserPanelProvider, jadi route `filament.user.auth.register`
// tidak terdaftar. Kode `App\Filament\User\Auth\SignUp\SignUp` beserta
// view-nya SENGAJA TIDAK DIHAPUS -- hanya baris pemanggilnya yang dikomentari
// (lihat "CARA MENGHIDUPKAN LAGI" di UserPanelProvider). Door `/user/signup`
// dan `/user/register` dilayani redirect ke `/user/auth`, satu-satunya halaman
// auth untuk tamu, karena pendaftaran sekarang hanya lewat tombol Google di
// sana (SocialiteController membuat akun otomatis).
Route::redirect('/user/register', '/user/auth');
Route::redirect('/user/signup', '/user/auth');
Route::redirect('/admin/login', '/admin/signin');

// -----------------------------------------------------------------------------
// SHARED — bahasa (kanonis: User\LanguageController; Admin & Welcome tersedia)
// -----------------------------------------------------------------------------
Route::get('/language/switch/{locale}', [LanguageController::class, 'switch'])
    ->name('language.switch');
Route::post('/language/locale/{locale}', [LanguageController::class, 'update'])
    ->name('language.update');
Route::get('/lang/switch/{locale}', [LanguageController::class, 'switch'])
    ->name('lang.switch');

// -----------------------------------------------------------------------------
// USER — OAuth web & mobile (kanonis: User\SocialiteController)
// (Admin\SocialiteController & Welcome\SocialiteController tersedia via extends)
// -----------------------------------------------------------------------------
Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('auth.callback');
// Callback OAuth untuk shell Capacitor. Redirect-nya tetap mendarat di server
// ini dan session cookie-nya langsung dipakai WebView — tidak ada token/deep
// link bridging seperti dulu. Rute lama sengaja dibiarkan supaya redirect URI
// yang masih terdaftar di konsol Google tidak berubah jadi 404.
Route::get('/auth/{provider}/callback/scheme', [SocialiteController::class, 'callbackMobileScheme'])
    ->name('auth.callback.scheme');
Route::get('/auth/{provider}/callback/mobile', [SocialiteController::class, 'callbackMobile'])
    ->name('auth.callback.mobile');
// Token handoff — bukan lagi bagian dari alur OAuth, tetap diterima bila ada token.
Route::get('/auth/mobile/verify', [SocialiteController::class, 'verifyMobileToken'])
    ->name('auth.mobile.verify');
// Jalur lama dari bridging deep link NativePHP; masih dilayani, bukan dihapus.
Route::get('/auth/deeplink/google/success', [SocialiteController::class, 'verifyMobileToken'])
    ->name('auth.deeplink.success');

// Jalur alias dari bridging deep link lama; shell Capacitor memuat URL
// server secara langsung, jadi tidak ada terjemahan scheme di sisi klien.
Route::get('/auth/google/success', [SocialiteController::class, 'verifyMobileToken'])
    ->name('auth.google.success');

// Google OAuth reverse client ID scheme callback
// com.googleusercontent.apps.xxx:/oauth2redirect?code=... → /auth/google/oauth2redirect?code=...
Route::get('/auth/{provider}/oauth2redirect', [SocialiteController::class, 'callbackMobileScheme'])
    ->name('auth.oauth2redirect');

// -----------------------------------------------------------------------------
// USER — Firebase Auth (kanonis: User\FirebaseAuthController)
// -----------------------------------------------------------------------------
// Firebase Auth: client sends ID token, backend verifies via REST API
Route::post('/auth/firebase/callback', [FirebaseAuthController::class, 'callback'])
    ->name('auth.firebase.callback');

// -----------------------------------------------------------------------------
// SHARED — Clerk login bridge (kanonis: User\ClerkLoginController)
// exchanges Sanctum token for session auth (for Filament access)
// (Admin\ClerkLoginController & Welcome\ClerkLoginController tersedia)
// -----------------------------------------------------------------------------
Route::get('/clerk/login', [ClerkLoginController::class, 'login'])
    ->name('clerk.login');

// -----------------------------------------------------------------------------
// SHARED — media publik (kanonis: User\MediaController)
// -----------------------------------------------------------------------------
Route::get('/media/{path}', [MediaController::class, 'serve'])
    ->where('path', '.*')->name('media.serve');

require __DIR__.'/../debug/debug.php';

// -----------------------------------------------------------------------------
// USER — PDF & dokumen (auth; pemilik atau super_admin)
// -----------------------------------------------------------------------------
// Invoice PDF — hanya untuk user yang login dan punya order tersebut
Route::get('/invoice/{order}/pdf', [InvoicePdfController::class, 'download'])
    ->middleware(['auth'])->name('invoice.pdf');

// Downloadable summary of the event-needs form shown after a bot reply.
Route::get('/messages/{inbox}/consultation-form.pdf', [ConsultationFormPdfController::class, 'download'])
    ->middleware(['auth'])->name('messages.consultation-form.pdf');

Route::get('/reports/{report}/pdf', [ReportPdfController::class, 'download'])
    ->middleware(['auth'])->name('user.reports.pdf');

// -----------------------------------------------------------------------------
// SHARED — vote "Membantu" via form POST biasa (bukan Livewire action),
// supaya guest tidak pernah memicu POST /livewire/update yang rawan 419.
// Guest ditolak di controller dan diarahkan ke halaman login.
// -----------------------------------------------------------------------------
Route::post('/reviews/{review}/helpful', [ReviewVoteController::class, 'toggle'])
    ->name('reviews.vote');
Route::post('/reviews/{review}/report', [ReviewReportController::class, 'store'])
    ->name('reviews.report');

// -----------------------------------------------------------------------------
// ADMIN — cermin PDF di bawah prefix /admin (middleware auth; cek role di controller)
// Memakai Admin\*Controller agar scope Admin lengkap & eksplisit.
// URI & nama route USER di atas tetap dipertahankan; ini tambahan.
// -----------------------------------------------------------------------------
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/invoice/{order}/pdf', [AdminInvoicePdfController::class, 'download'])
        ->name('invoice.pdf');
    Route::get('/messages/{inbox}/consultation-form.pdf', [AdminConsultationFormPdfController::class, 'download'])
        ->name('messages.consultation-form.pdf');
    Route::get('/reports/{report}/pdf', [AdminReportPdfController::class, 'download'])
        ->name('reports.pdf');
    Route::post('/reviews/{review}/helpful', [AdminReviewVoteController::class, 'toggle'])
        ->name('reviews.vote');
    Route::get('/data-exports/download/{file}', [DataExportDownloadController::class, 'download'])
        ->middleware(SuperAdmin::class)
        ->where('file', '[A-Za-z0-9\-.]+\.(zip|xlsx|pdf)')
        ->name('data-exports.download');
});

// -----------------------------------------------------------------------------
// WELCOME — cermin PDF di bawah prefix /welcome (middleware auth; cek di controller)
// Memakai Welcome\*Controller agar scope Welcome lengkap & eksplisit.
// URI & nama route USER di atas tetap dipertahankan; ini tambahan.
// -----------------------------------------------------------------------------
Route::middleware(['auth'])->prefix('welcome')->name('welcome.')->group(function (): void {
    Route::get('/invoice/{order}/pdf', [WelcomeInvoicePdfController::class, 'download'])
        ->name('invoice.pdf');
    Route::get('/messages/{inbox}/consultation-form.pdf', [WelcomeConsultationFormPdfController::class, 'download'])
        ->name('messages.consultation-form.pdf');
    Route::post('/reviews/{review}/helpful', [WelcomeReviewVoteController::class, 'toggle'])
        ->name('reviews.vote');
    Route::post('/reviews/{review}/report', [WelcomeReviewReportController::class, 'store'])
        ->name('reviews.report');
});

// PDF laporan -- SENGAJA di luar middleware auth, dan tetap memakai prefix
// serta name yang sama (welcome.*) supaya URL-nya tidak berubah.
//
// Panel Welcome melayani tamu, dan laporan bug bisa dibuat oleh tamu lewat
// formulir di halaman Messages. Dengan auth, tamu yang melapor akan
// mendarat di halaman login alih-alih menerima PDF-nya.
//
// Kepemilikan dijaga di controller Welcome\ReportPdfController lewat
// GuestIdentity: hanya pelapor (atau super_admin) yang boleh, selain itu 403.
Route::prefix('welcome')->name('welcome.')->group(function (): void {
    Route::get('/reports/{report}/pdf', [WelcomeReportPdfController::class, 'download'])
        ->name('reports.pdf');
});

// -----------------------------------------------------------------------------
// COOKIE CONSENT — GDPR/ePrivacy compliant
// -----------------------------------------------------------------------------
Route::prefix('cookie-consent')->name('cookie-consent.')->group(function (): void {
    Route::post('/accepted', [CookieConsentController::class, 'accept'])->name('accept');
    Route::post('/essential_only', [CookieConsentController::class, 'essentialOnly'])->name('essential');
    Route::post('/rejected', [CookieConsentController::class, 'reject'])->name('reject');
    Route::post('/reset', [CookieConsentController::class, 'reset'])->name('reset');
    Route::get('/status', [CookieConsentController::class, 'status'])->name('status');
});

// Cookie Preferences Page (accessible from banner link)
Route::get('/cookie-preferences', function () {
    return view('components.cookie-consent.preferences');
})->name('cookie.preferences');

// CAPTCHA Route (mews/captcha)
Route::get('/captcha/image', function () {
    return response(Captcha::img())
        ->header('Content-Type', 'image/png')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
})->name('captcha.image');
