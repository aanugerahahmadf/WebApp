<?php

use App\Http\Controllers\Auth\FirebaseAuthController\FirebaseAuthController;
use App\Http\Controllers\Auth\SocialiteController\SocialiteController;
use App\Http\Controllers\LanguageController\LanguageController;
use App\Http\Controllers\LegalWebController\LegalWebController;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Models\Order\Order;
use App\Models\Inbox\Inbox;
use App\Models\User\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
// Legal Pages — HTML untuk mobile browser / HP fisik
use Native\Mobile\Facades\System;

Route::get('/legal/terms', [LegalWebController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [LegalWebController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/help', [LegalWebController::class, 'help'])->name('legal.help');

Route::get('/', function () {
    return view('User.welcome.welcome');
})->middleware(SetLocale::class);

Route::redirect('/admin/inbox', '/admin/inbox/messages');
Route::get('/mobile/settings', function () {
    System::appSettings();

    return back();
})->name('mobile.settings')->middleware(['auth']);
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
// Mobile OAuth via reverse client ID scheme (tidak perlu server publik)
Route::get('/auth/{provider}/callback/scheme', [SocialiteController::class, 'callbackMobileScheme'])
    ->name('auth.callback.scheme');
// Mobile OAuth: callback dari Google → simpan token → redirect ke deep link
Route::get('/auth/{provider}/callback/mobile', [SocialiteController::class, 'callbackMobile'])
    ->name('auth.callback.mobile');
// Mobile OAuth: deep link handler → verifikasi token → login user
Route::get('/auth/mobile/verify', [SocialiteController::class, 'verifyMobileToken'])
    ->name('auth.mobile.verify');
// NativePHP Deep Link Handler — weddingapp://auth/google/success?token=xxx
// NativePHP intercepts the deep link and loads this URL in the WebView
Route::get('/auth/deeplink/google/success', [SocialiteController::class, 'verifyMobileToken'])
    ->name('auth.deeplink.success');

// NativePHP juga bisa load path langsung dari deep link
// weddingapp://auth/google/success → /auth/google/success di WebView
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
