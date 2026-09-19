<?php

use App\Http\Controllers\PlatformCameraController\PlatformCameraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile-Specific API Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when the application is running in
| Mobile Native Mode (php artisan native:run). They are loaded by the
| RouteServiceProvider under the "api/mobile" prefix when PlatformMode
| is Mobile.
|
| All routes here assume NativePHP Mobile APIs are available.
|
*/

// ── Camera / CBIR ────────────────────────────────────────────────────────────
// POST /api/mobile/camera/capture
// Trigger native mobile camera capture for CBIR (Content-Based Image Retrieval).
Route::post('/camera/capture', [PlatformCameraController::class, 'capture']);

// ── File System ──────────────────────────────────────────────────────────────
// POST /api/mobile/file/upload
// Upload a file from the mobile device file system.
Route::post('/file/upload', function () {
    return response()->json([
        'status' => 'available',
        'platform' => 'mobile',
        'feature' => 'file_system',
        'message' => 'Mobile file upload is available via NativePHP Mobile File System API.',
        'hint' => 'Attach multipart/form-data with a "file" field containing the file to upload.',
    ]);
});

// ── Push Notifications ───────────────────────────────────────────────────────
// GET /api/mobile/notifications
// Retrieve the current push notification status and pending notification count.
Route::get('/notifications', function () {
    return response()->json([
        'status' => 'available',
        'platform' => 'mobile',
        'feature' => 'push_notifications',
        'message' => 'Push notifications are available on this mobile platform.',
        'hint' => 'Use the FCM / APNs token registered for this device to send push notifications.',
        'data' => [
            'push_enabled' => true,
            'pending_count' => 0,
            'notification_types' => ['order_update', 'message', 'promotion'],
        ],
    ]);
});
