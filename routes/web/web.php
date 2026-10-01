<?php

use App\Http\Controllers\Auth\FirebaseAuthController\FirebaseAuthController;
use App\Http\Controllers\Auth\SocialiteController\SocialiteController;
use App\Http\Controllers\LanguageController\LanguageController;
use App\Http\Controllers\LegalWebController\LegalWebController;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Models\Order\Order;
use App\Models\Inbox\Inbox;
use App\Models\User\User;
use App\Models\Report\Report;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

// Legal Pages — HTML untuk mobile browser / HP fisik

Route::get('/legal/terms', [LegalWebController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [LegalWebController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/help', [LegalWebController::class, 'help'])->name('legal.help');

// The storefront is the 'welcome' Filament panel, and '/' is its front door.
// The old marketing page that used to live here has been removed: guests browse
// the landing page and the catalog, and anything account-shaped sends them to
// the user panel's login (see AuthenticateWelcome).
Route::redirect('/', '/welcome/home')->middleware(SetLocale::class);

Route::redirect('/admin/inbox', '/admin/inbox/messages');
Route::get('/language/switch/{locale}', [LanguageController::class, 'switch'])
    ->name('language.switch');
Route::post('/language/locale/{locale}', [LanguageController::class, 'update'])
    ->name('language.update');
Route::get('/lang/switch/{locale}', [LanguageController::class, 'switch'])
    ->name('lang.switch');
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

// Clerk login bridge — exchanges Sanctum token for session auth (for Filament access)
Route::get('/clerk/login', function (Request $request) {
    $token = $request->query('token');
    if (! $token) {
        return redirect('/admin/login')->with('error', 'Token tidak ditemukan');
    }

    $accessToken = PersonalAccessToken::findToken($token);
    if (! $accessToken) {
        return redirect('/admin/login')->with('error', 'Token tidak valid');
    }

    $user = $accessToken->tokenable;
    if (! $user || ! $user instanceof User) {
        return redirect('/admin/login')->with('error', 'Pengguna tidak ditemukan');
    }

    Auth::login($user);

    $panel = $user->hasRole('super_admin') ? 'admin' : 'user';

    return redirect("/{$panel}");
})->name('clerk.login');

// Firebase Auth: client sends ID token, backend verifies via REST API
Route::post('/auth/firebase/callback', [FirebaseAuthController::class, 'callback'])
    ->name('auth.firebase.callback');

// Google OAuth reverse client ID scheme callback
// com.googleusercontent.apps.xxx:/oauth2redirect?code=... → /auth/google/oauth2redirect?code=...
Route::get('/auth/{provider}/oauth2redirect', [SocialiteController::class, 'callbackMobileScheme'])
    ->name('auth.oauth2redirect');
Route::get('/media/{path}', function (string $path) {
    if (str_contains($path, '../')) {
        abort(403);
    }
    $file = storage_path('app/public/'.$path);
    if (! file_exists($file)) {
        abort(404);
    }

    return response()->file($file, ['Content-Type' => File::mimeType($file)]);
})->where('path', '.*')->name('media.serve');

require __DIR__.'/../debug/debug.php';

// Invoice PDF — hanya untuk user yang login dan punya order tersebut
Route::get('/invoice/{order}/pdf', function (Order $order) {
    // Pastikan hanya pemilik order yang bisa akses
    if (auth()->id() !== $order->user_id && ! auth()->user()?->hasRole('super_admin')) {
        abort(403);
    }

    $order->load(['user', 'package.category', 'package.media',
        'product.category', 'product.media',
        'latestTransaction']);

    $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();

    $dompdf = new Dompdf;
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'invoice-'.$order->order_number.'.pdf';
    $inline = request()->boolean('download') ? 'attachment' : 'inline';

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "{$inline}; filename=\"{$filename}\"",
    ]);
})->middleware(['auth'])->name('invoice.pdf');

// Downloadable summary of the event-needs form shown after a bot reply.
Route::get('/messages/{inbox}/consultation-form.pdf', function (Inbox $inbox) {
    $userId = auth()->id();
    if (! in_array($userId, $inbox->user_ids ?? []) && ! auth()->user()?->hasRole('super_admin')) {
        abort(403);
    }

    $forms = $inbox->meta['consultation_forms'] ?? [];
    $form = $forms[(string) $userId] ?? null;
    if (! is_array($form)) {
        abort(404, 'Formulir belum diisi.');
    }

    $html = view('User.pdf.consultation-form.consultation-form', compact('form', 'inbox'))->render();
    $dompdf = new Dompdf;
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="formulir-kebutuhan-acara-'.$inbox->id.'.pdf"',
    ]);
})->middleware(['auth'])->name('messages.consultation-form.pdf');

Route::get('/reports/{report}/pdf', function (Report $report) {
    abort_unless((int) $report->user_id === (int) auth()->id(), 403);

    $categoryLabels = [
        'bug_report' => __('Lapor Bug'),
        'account_issue' => __('Masalah Akun'),
        'order_help' => __('Bantuan Pesanan'),
        'payment_issue' => __('Masalah Pembayaran'),
        'decor_consultation' => __('Konsultasi Dekorasi'),
        'general_question' => __('Pertanyaan Umum'),
    ];
    $userName = e($report->user?->full_name ?? '-');
    $category = e($categoryLabels[$report->category] ?? $report->category);
    $reason = e($report->reason ?? '-');
    $description = nl2br(e($report->description ?? '-'));
    $createdAt = e($report->created_at?->format('d/m/Y H:i') ?? '-');
    $attachments = collect($report->attachment_urls)
        ->map(fn ($url) => '<p><a href="'.e($url).'">'.e($url).'</a></p>')
        ->implode('');
    $html = "<html><meta charset='utf-8'><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}h1{font-size:20px}</style><h1>Formulir Laporan #{$report->id}</h1><p><b>Pelapor:</b> {$userName}</p><p><b>Kategori:</b> {$category}</p><p><b>Judul:</b> {$reason}</p><p><b>Tanggal:</b> {$createdAt}</p><p><b>Detail:</b><br>{$description}</p><h3>Lampiran</h3>{$attachments}</html>";
    $dompdf = new Dompdf;
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="laporan-'.$report->id.'.pdf"',
    ]);
})->middleware(['auth'])->name('user.reports.pdf');
