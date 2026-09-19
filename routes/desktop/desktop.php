<?php

use App\Http\Controllers\PlatformCameraController\PlatformCameraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop-Specific API Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when the application is running in
| Desktop App Mode (php artisan native:serve). They are loaded by the
| bootstrap/app.php under the "api/desktop" prefix when PlatformMode
| is Desktop.
|
| All routes here assume NativePHP Electron (desktop) APIs are available.
|
*/

// ── Camera / CBIR ────────────────────────────────────────────────────────────
// POST /api/desktop/camera/capture
// Trigger native desktop camera capture for CBIR (Content-Based Image Retrieval).
Route::post('/camera/capture', [PlatformCameraController::class, 'capture']);

// ── File System ──────────────────────────────────────────────────────────────
// POST /api/desktop/file/save
// Save a file to the desktop file system.
Route::post('/file/save', function () {
    return response()->json([
        'status' => 'available',
        'platform' => 'desktop',
        'feature' => 'file_system',
        'message' => 'Desktop file system access is available via NativePHP Electron File System APIs.',
        'hint' => 'Use NativePHP native file dialogs and file system APIs to save files.',
    ]);
});

// ── Desktop Notifications ────────────────────────────────────────────────────
// GET /api/desktop/notifications
// Retrieve the current desktop notification status.
Route::get('/notifications', function () {
    return response()->json([
        'status' => 'available',
        'platform' => 'desktop',
        'feature' => 'desktop_notifications',
        'message' => 'Desktop notifications are available on this platform.',
        'hint' => 'Use NativePHP Notification APIs to send native OS notifications.',
        'data' => [
            'notifications_enabled' => true,
            'notification_types' => ['order_update', 'message', 'system'],
        ],
    ]);
});

// ── Auto Updates ─────────────────────────────────────────────────────────────
// GET /api/desktop/updates/check
// Check for available desktop application updates.
Route::get('/updates/check', function () {
    return response()->json([
        'status' => 'available',
        'platform' => 'desktop',
        'feature' => 'auto_updates',
        'message' => 'Auto-update check is available on this desktop platform.',
        'hint' => 'Use NativePHP Auto Updater to check and apply application updates.',
        'data' => [
            'update_available' => false,
            'current_version' => config('app.version', '1.0.0'),
        ],
    ]);
});
