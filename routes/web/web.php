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
use App\Http\Controllers\Welcome\LegalWebController\LegalWebController as WelcomeLegalWebController;
use App\Http\Controllers\Welcome\DocumentationController\DocumentationController;
use App\Http\Controllers\Welcome\ReportPdfController\ReportPdfController as WelcomeReportPdfController;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\SuperAdmin\SuperAdmin;
use App\Http\Controllers\ChatCall\ChatCallController;
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
// Native app boot screen. Capacitor starts here so Android, iOS, and Electron
// can prepare the session/data before showing the first panel page.
Route::get('/app-loading', function (\Illuminate\Http\Request $request) {
    $target = (string) $request->query('target', '/home');

    // Never allow the boot screen to redirect to an external URL.
    if (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
        $target = '/home';
    }

    return view('Shared.pages.app-loading.app-loading', compact('target'));
})->middleware(SetLocale::class)->name('app.loading');

// Legal Pages — HTML untuk mobile browser / HP fisik (scope Welcome).
Route::get('/legal/terms', [WelcomeLegalWebController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [WelcomeLegalWebController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/help', [WelcomeLegalWebController::class, 'help'])->name('legal.help');

// Dokumentasi teknis publik yang dirender langsung dari folder /docs.
Route::get('/docs', [DocumentationController::class, 'index'])->name('docs.index');
Route::get('/docs/code/{path}', [DocumentationController::class, 'code'])
    ->where('path', '.*')
    ->name('docs.code');
Route::get('/docs/code-snippet/{index}/{path}', [DocumentationController::class, 'snippet'])
    ->where(['index' => '[0-9]+', 'path' => '.*'])
    ->name('docs.snippet');
Route::get('/docs/{path}', [DocumentationController::class, 'show'])
    ->where('path', '.*')
    ->name('docs.show');

// Token CSRF terbaru, untuk pemulihan diam-diam di sisi browser.
//
// Token sesi berubah setiap kali user login (OTP/2FA memanggil
// `regenerateToken()`), jadi tab yang sudah terbuka sebelum login masih
// memegang token lama. Endpoint ini dipakai skrip
// `Shared.components.livewire-page-expired` untuk mengambil token baru dan
// mengulang request-nya sendiri -- tanpa memunculkan dialog "This page has
// expired" dan tanpa memuat ulang halaman. GET, tanpa state, tanpa auth:
// token CSRF memang tidak rahasia (ia sudah ada di <meta> setiap halaman).
Route::get('/livewire/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('livewire.csrf-token');

// Alias ber-prefix /welcome DIHAPUS — tidak boleh ada URL 'welcome'.
// Halaman legal yang sah ada di /legal/terms, /legal/privacy, /legal/help;
// bookmark /welcome/legal/* jatuh ke catch-all LEGACY WELCOME di akhir file
// ini dan mendarat di halaman yang sama.

// The storefront is the 'welcome' Filament panel, and it lives at the ROOT
// domain, shopee-style: /home, /messages, /flowerdecorationscatalog, dst --
// satu ruang URL untuk tamu dan shopper yang sign in (akun tetap di /user/*,
// seperti /account milik Shopee).
// '/' sadar-auth: yang login di guard `web` -> home akun user (/user/home);
// tamu -> storefront beranda (/home).
//
// Catatan: super admin TIDAK ikut diarahkan ke sini lewat shortcut. Admin
// login di guard `admin` (tabel `admins`), jadi `auth()->check()` di sini
// selalu false baginya -- dia dianggap tamu, seperti tamu mana pun. Itu
// memang yang benar: panel admin punya pintunya sendiri di `/admin`, dan
// admin yang juga butuh panel user memakai identitas belanja yang di-grant
// (`php artisan admin:grant-storefront`), bukan akun admin-nya.
Route::get('/', function () {
    // SATU RUANG URL (shopee-style): tidak ada lagi /user/* maupun /welcome/*.
    // Tamu DAN shopper yang sudah sign in mendarat di storefront /home yang
    // sama; pintu akun ada di topbar (dropdown tamu / user menu Filament).
    return redirect('/home');
})->middleware(SetLocale::class);

Route::redirect('/admin/inbox', '/admin/inbox/messages');

// -----------------------------------------------------------------------------
// Alias URL katalog panel admin.
//
// Resource katalog memakai slug `catalogs` -> /admin/catalogs. Tapi URL lama
// yang dipakai bookmark dan tautan lama adalah /admin/flowerdecorationscatalog
// (slug yang sama dengan resource katalog di panel Welcome/User).
//
// Tanpa redirect di sini, bookmark itu berakhir 404 -- padahal halamannya
// hanya pindah nama. Redirect memakai 302 supaya query param `?tab=` ikut
// dibawa: /admin/flowerdecorationscatalog?tab=package harus tetap membuka tab
// Paket, bukan kembali ke tab default.
// -----------------------------------------------------------------------------
Route::get('/admin/flowerdecorationscatalog', fn (\Illuminate\Http\Request $request) => redirect('/admin/catalogs'.($request->getQueryString() ? '?'.$request->getQueryString() : '')));

// -----------------------------------------------------------------------------
// Alias URL auth LAMA — DIHAPUS, bukan dipindah.
//
// /signin, /signup, /auth sekarang adalah route PANEL itu sendiri (panel
// 'welcome' di root). Redirect di URI yang sama di file ini akan MEMBLOKIR
// route panel, karena web.php diregistrasi lebih dulu -- persis yang terjadi
// saat merge: /signin berbalik 302 ke /user/signin yang sudah tidak ada.
// Karena itu barisnya dihapus; tidak ada /user/* yang tersisa untuk
// dialihkan selain bookmark lama di blok LEGACY USER paling bawah.
//
// Panel admin tetap di /admin/* tanpa perubahan.
// -----------------------------------------------------------------------------
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
    Route::redirect('/website-customizer', '/admin/website-mobile-customizer');
});

// -----------------------------------------------------------------------------
// LAPORAN PDF (guest) — dulu /welcome/reports/{report}/pdf.
//
// Nama route `welcome.reports.pdf` DIPERTAHANKAN (blade & Livewire Messages
// memanggilnya, dan test memakai nama ini), tapi URL-nya tidak lagi mengandung
// segmen 'welcome': /reports/guest/{report}/pdf.
//
// /reports/{report}/pdf sudah dipakai route auth kanonik `user.reports.pdf`,
// jadi segmen 'guest' memisahkan keduanya.
//
// SENGAJA di luar middleware auth, sama seperti sebelumnya: laporan bug bisa
// dibuat TAMU lewat formulir di halaman Messages, dan tamu itu harus bisa
// mengunduh PDF-nya. Kepemilikan dijaga di controller Welcome\ReportPdfController
// lewat GuestIdentity: hanya pelapor (atau super_admin) yang boleh, selain itu 403.
// -----------------------------------------------------------------------------
Route::get('/reports/guest/{report}/pdf', [WelcomeReportPdfController::class, 'download'])
    ->name('welcome.reports.pdf');

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

// POST /welcome/logout: sama seperti /user/logout, tombol Sign Out dari
// halaman lama masih mengirim ke sini. Route::redirect hanya melayani GET,
// jadi tanpa handler eksplisit POST-nya berakhir 405.
Route::any('/welcome/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/home');
});

// -----------------------------------------------------------------------------
// LEGACY /welcome/* - bookmark lama dari saat panel 'welcome' masih
// ber-prefix /welcome. Panel pindah ke path '/' (shopee-style), jadi semua
// URL lama diarahkan ke padanannya di root: /welcome/home -> /home,
// /welcome/flowerdecorationscatalog -> /flowerdecorationscatalog, dst.
//
// Didaftarkan di AKHIR file supaya route /welcome/* yang eksplisit dan
// masih sah (welcome.legal.*, welcome.reports.pdf) tetap menang: redirect
// ini hanya menyisakan yang tidak lagi punya penangan khusus.
// Query string dipertahankan otomatis oleh Route::redirect.
// -----------------------------------------------------------------------------
Route::redirect('/welcome/{path}', '/{path}')
    ->where('path', '.*');
Route::redirect('/welcome', '/home');

// -----------------------------------------------------------------------------
// LEGACY /user/* — panel 'user' sudah DILEBUR ke panel 'welcome' di root
// (shopee-style). TIDAK ADA lagi URL /user/* yang melayani halaman; kalau
// masih ada bookmark, semuanya diarahkan PERMANEN ke padanannya di root:
//
//   /user/home      -> /home
//   /user/signin    -> /signin       (halaman auth panel itu sendiri)
//   /user/settings  -> /settings
//   /user/o-t-p-verify -> /o-t-p-verify
//
// Didaftarkan paling akhir supaya tidak menabrak route panel di root.
// Query string dipertahankan otomatis oleh Route::redirect.
// -----------------------------------------------------------------------------
// Alias lama di root: /login dan /register masih nongol di bookmark lama.
// Tanpa baris ini keduanya 404 -- padahal /login adalah pintu yang paling
// sering diketik orang. Dipaksa ke panel auth yang SAMA supaya tidak ada
// dua halaman login yang hidup bersamaan.
// Query string tetap ikut karena Route::redirect meneruskannya.
Route::redirect('/login', '/signin');
Route::redirect('/register', '/signup');

// First-party WebRTC signaling for the three web chat panels. The controller
// resolves the current web/admin session or the Welcome guest cookie.
Route::prefix('chat-calls')->group(function (): void {
    Route::post('/', [ChatCallController::class, 'start']);
    Route::get('/{inboxId}/active', [ChatCallController::class, 'active']);
    Route::get('/{token}', [ChatCallController::class, 'state']);
    Route::post('/{token}/signal', [ChatCallController::class, 'signal']);
    Route::post('/{token}/accept', [ChatCallController::class, 'accept']);
    Route::post('/{token}/end', [ChatCallController::class, 'end']);
});

// Alias /user/* yang TIDAK bisa ikut catch-all di bawah, karena padanannya
// di root bernama berbeda (register -> signup, login -> signin). Tanpa ini
// /user/register berakhir di /register yang 404.
Route::redirect('/user/register', '/signup');
Route::redirect('/user/login', '/signin');

// POST /user/logout: tombol Sign Out di halaman lama masih mengirim ke sini.
// Ditangani eksplisit karena Route::redirect hanya melayani GET; tanpa ini
// tombol lama berakhir 404/405. CSRF tetap dicek (rute web), dan
// App\Http\Middleware\VerifyCsrfToken memang mengecualikan user/logout, jadi
// form lama dengan token basi tetap bisa logout -- bukan lubang baru, logout
// tidak mengubah data apa pun.
Route::any('/user/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/home');
});

Route::redirect('/user/{path}', '/{path}')
    ->where('path', '.*');
Route::redirect('/user', '/home');
