<?php

use App\Http\Controllers\Api\User\AppSettingsController\AppSettingsController;
use App\Http\Controllers\DatabaseProxyController\DatabaseProxyController;
use App\Http\Middleware\SuperAdmin\SuperAdmin;
use App\Models\User\User;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and are grouped with the
| "api" middleware group. Route definitions dipisah per controller ke
| direktori routes/api agar mengikuti struktur controller:
|
|   app/Http/Controllers/Api/User/<Name>/<Name>.php     → namespace App\Http\Controllers\Api\User\<Name>\<Name>
|   app/Http/Controllers/Api/Admin/<Name>/<Name>.php    → namespace App\Http\Controllers\Api\Admin\<Name>\<Name>
|   routes/api/user/<Name>/<Name>.php                   → route file (public + auth:sanctum)
|   routes/api/admin/<Name>/<Name>.php                  → route file (prefix admin + auth:sanctum + SuperAdmin)
|
*/

// -----------------------------------------------------------------------------
// PUBLIC: APP CONFIG
// -----------------------------------------------------------------------------
Route::get('/settings', [AppSettingsController::class, 'index']);

// -----------------------------------------------------------------------------
// DIAGNOSTIC PING — test sinkronisasi mobile (GET /api/ping)
// -----------------------------------------------------------------------------
Route::get('/ping', function () {
    $isMobile = NativeServiceProvider::isNativeMobile();
    $hostIp = NativeServiceProvider::mobileHostIp();

    $dbStatus = 'unknown';
    $userCount = 0;
    $dbError = null;

    try {
        $userCount = User::count();
        $dbStatus = 'connected';
    } catch (Throwable $e) {
        $dbStatus = 'error';
        $dbError = $e->getMessage();
    }

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'is_mobile' => $isMobile,
        'host_ip' => $hostIp,
        'os' => PHP_OS_FAMILY,
        'db_driver' => config('database.default'),
        'db_status' => $dbStatus,
        'user_count' => $userCount,
        'db_error' => $dbError,
        'app_url' => config('app.url'),
        'locale' => app()->getLocale(),
        'php_version' => PHP_VERSION,
    ]);
});

// NativePHP Mobile DB Proxy — lokal/testing saja, dilindungi X-DB-PROXY-SECRET
if (App::environment('local', 'testing')) {
    Route::post('/db-proxy', [DatabaseProxyController::class, 'proxy']);
}

// -----------------------------------------------------------------------------
// ROOT CONTROLLERS
// -----------------------------------------------------------------------------
require __DIR__.'/DatabaseProxyController/DatabaseProxyController/DatabaseProxyController.php';
require __DIR__.'/PusherAuthController/PusherAuthController/PusherAuthController.php';

// -----------------------------------------------------------------------------
// USER (MOBILE APP) — public + auth:sanctum per controller
// -----------------------------------------------------------------------------
require __DIR__.'/user/AuthController/AuthController/AuthController.php';
require __DIR__.'/user/AppSettingsController/AppSettingsController/AppSettingsController.php';
require __DIR__.'/user/CBIRController/CBIRController/CBIRController.php';
require __DIR__.'/user/CategoryController/CategoryController/CategoryController.php';
require __DIR__.'/user/PackageController/PackageController/PackageController.php';
require __DIR__.'/user/ProductController/ProductController/ProductController.php';
require __DIR__.'/user/SearchController/SearchController/SearchController.php';
require __DIR__.'/user/CartController/CartController/CartController.php';
require __DIR__.'/user/WishlistController/WishlistController/WishlistController.php';
require __DIR__.'/user/OrderController/OrderController/OrderController.php';
require __DIR__.'/user/VendorController/VendorController/VendorController.php';
require __DIR__.'/user/VoucherController/VoucherController/VoucherController.php';
require __DIR__.'/user/NotificationController/NotificationController/NotificationController.php';
require __DIR__.'/user/ReviewController/ReviewController/ReviewController.php';
require __DIR__.'/user/ChatController/ChatController/ChatController.php';
require __DIR__.'/user/LegalController/LegalController/LegalController.php';
require __DIR__.'/user/WalletController/WalletController/WalletController.php';
require __DIR__.'/user/SecurityController/SecurityController/SecurityController.php';
require __DIR__.'/user/LoginActivityController/LoginActivityController/LoginActivityController.php';
require __DIR__.'/user/ProfileController/ProfileController/ProfileController.php';
require __DIR__.'/user/AppLockController/AppLockController/AppLockController.php';
require __DIR__.'/user/UserLanguageController/UserLanguageController/UserLanguageController.php';
require __DIR__.'/user/RegionController/RegionController/RegionController.php';
require __DIR__.'/user/WorldRegionController/WorldRegionController/WorldRegionController.php';
require __DIR__.'/user/GeoController/GeoController/GeoController.php';
require __DIR__.'/user/DropdownOptionController/DropdownOptionController/DropdownOptionController.php';
require __DIR__.'/user/FirebaseController/FirebaseController/FirebaseController.php';
require __DIR__.'/user/HistoryController/HistoryController/HistoryController.php';
require __DIR__.'/user/HomeController/HomeController/HomeController.php';
require __DIR__.'/user/TransactionController/TransactionController/TransactionController.php';
require __DIR__.'/user/ReportController/ReportController/ReportController.php';
require __DIR__.'/user/LegalPageController/LegalPageController/LegalPageController.php';
require __DIR__.'/user/FonnteWebhookController/FonnteWebhookController/FonnteWebhookController.php';
require __DIR__.'/user/BriVaWebhookController/BriVaWebhookController/BriVaWebhookController.php';
require __DIR__.'/user/PaymentWebhookController/PaymentWebhookController/PaymentWebhookController.php';

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('admin')->middleware(SuperAdmin::class)->group(function (): void {
        require __DIR__.'/admin/SearchController/SearchController/SearchController.php';
        require __DIR__.'/admin/DashboardController/DashboardController/DashboardController.php';
        require __DIR__.'/admin/UserController/UserController/UserController.php';
        require __DIR__.'/admin/PackageController/PackageController/PackageController.php';
        require __DIR__.'/admin/ProductController/ProductController/ProductController.php';
        require __DIR__.'/admin/CategoryController/CategoryController/CategoryController.php';
        require __DIR__.'/admin/OrderController/OrderController/OrderController.php';
        require __DIR__.'/admin/TransactionController/TransactionController/TransactionController.php';
        require __DIR__.'/admin/ReviewController/ReviewController/ReviewController.php';
        require __DIR__.'/admin/DiscountController/DiscountController/DiscountController.php';
        require __DIR__.'/admin/VoucherController/VoucherController/VoucherController.php';
        require __DIR__.'/admin/PaymentMethodController/PaymentMethodController/PaymentMethodController.php';
        require __DIR__.'/admin/PaymentGatewayController/PaymentGatewayController/PaymentGatewayController.php';
        require __DIR__.'/admin/BankController/BankController/BankController.php';
        require __DIR__.'/admin/ReferenceOptionController/ReferenceOptionController/ReferenceOptionController.php';
        require __DIR__.'/admin/LegalPageController/LegalPageController/LegalPageController.php';
        require __DIR__.'/admin/HelpController/HelpController/HelpController.php';
        require __DIR__.'/admin/NotificationController/NotificationController/NotificationController.php';
        require __DIR__.'/admin/MessageController/MessageController/MessageController.php';
        require __DIR__.'/admin/WishlistController/WishlistController/WishlistController.php';
        require __DIR__.'/admin/ReportController/ReportController/ReportController.php';
    });
});