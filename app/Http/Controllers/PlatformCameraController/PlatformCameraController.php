<?php

namespace App\Http\Controllers\PlatformCameraController;

use App\Http\Controllers\Controller;

use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Native\Laravel\Facades\Window;
use Native\Mobile\Camera;

/**
 * Platform-aware camera controller.
 *
 * Delegates camera capture requests to the appropriate platform-specific
 * API based on the current RuntimePlatform detected at runtime.
 *
 * - MobileApp (Android/iOS)  → NativePHP Mobile Camera API (Native\Mobile\Camera)
 * - DesktopApp (Win/macOS)   → NativePHP Desktop Camera API
 * - Website (all browsers)   → WebRTC getUserMedia instructions
 */
class PlatformCameraController extends Controller
{
    /**
     * Handle a platform-aware camera capture request.
     *
     * Uses RuntimePlatform::cbirCameraMode() to determine the appropriate
     * camera mode and delegates to the correct platform API.
     */
    public function capture(Request $request): JsonResponse
    {
        /** @var RuntimePlatform $platform */
        $platform = app('runtime.platform');

        $cameraMode = $platform->cbirCameraMode();

        if ($platform->isMobileApp()) {
            return $this->captureViaMobileCamera($platform, $cameraMode);
        }

        if ($platform->isDesktopApp()) {
            return $this->captureViaDesktopCamera($platform, $cameraMode);
        }

        // Website platforms fall through to WebRTC
        return $this->captureViaWebRTC($platform, $cameraMode);
    }

    /**
     * Capture using the NativePHP Mobile Camera API.
     *
     * Attempts to use Native\Mobile\Camera when the class is available;
     * otherwise returns JSON instructions for the mobile client.
     */
    protected function captureViaMobileCamera(RuntimePlatform $platform, string $cameraMode): JsonResponse
    {
        // Guard: use class_exists() so the app doesn't crash when the
        // NativePHP Mobile package is not installed.
        if (class_exists(Camera::class)) {
            try {
                // Trigger native camera capture.
                // The NativePHP Mobile Camera API is event-driven; the
                // result arrives via a broadcast event (CameraPhotoTaken).
                // We fire the request here and acknowledge it.
                Camera::photo();

                return response()->json([
                    'camera_mode' => $cameraMode,
                    'platform' => $platform->value,
                    'status' => 'triggered',
                    'instructions' => 'Native mobile camera capture initiated. '
                        .'Listen for the CameraPhotoTaken event to receive the image.',
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'camera_mode' => $cameraMode,
                    'platform' => $platform->value,
                    'status' => 'error',
                    'instructions' => 'Mobile camera capture failed: '.$e->getMessage(),
                ], 500);
            }
        }

        // NativePHP Mobile package not installed – return client-side instructions.
        return response()->json([
            'camera_mode' => $cameraMode,
            'platform' => $platform->value,
            'status' => 'instructions',
            'instructions' => 'Use the Native\\Mobile\\Camera API to capture an image '
                .'and submit it to /api/cbir/search.',
            'api_class' => 'Native\\Mobile\\Camera',
            'method' => 'photo()',
        ]);
    }

    /**
     * Capture using the NativePHP Desktop Camera API.
     *
     * NativePHP Desktop exposes screen/window capture via the Electron
     * desktopCapturer API, surfaced through NativePHP's PHP bindings.
     */
    protected function captureViaDesktopCamera(RuntimePlatform $platform, string $cameraMode): JsonResponse
    {
        // Check for NativePHP Desktop Window / screen capture class.
        if (class_exists(Window::class)) {
            return response()->json([
                'camera_mode' => $cameraMode,
                'platform' => $platform->value,
                'status' => 'available',
                'instructions' => 'Use NativePHP Electron desktopCapturer APIs to capture '
                    .'a webcam image and submit it to /api/cbir/search.',
                'api_class' => 'Native\\Laravel\\Facades\\Window',
                'hint' => 'Trigger camera access via the Electron renderer process '
                    .'using navigator.mediaDevices.getUserMedia() within the NativePHP window.',
            ]);
        }

        // NativePHP Desktop package not installed – return instructions.
        return response()->json([
            'camera_mode' => $cameraMode,
            'platform' => $platform->value,
            'status' => 'instructions',
            'instructions' => 'Desktop camera capture is available via NativePHP Desktop Camera API. '
                .'Ensure the nativephp/electron package is installed.',
            'api_package' => 'nativephp/electron',
        ]);
    }

    /**
     * Get the platform-appropriate permission denial message for camera access.
     *
     * Returns a non-empty, human-readable error message tailored to the
     * permission model of the given platform:
     *  - Website platforms → browser permission settings
     *  - Mobile apps       → OS Settings > Privacy
     *  - Desktop apps      → application-level camera permission
     */
    public static function getPlatformPermissionDeniedMessage(RuntimePlatform $platform): string
    {
        if ($platform->isWebsite()) {
            return 'Camera access denied. Please allow camera access in your browser settings.';
        }

        if ($platform->isMobileApp()) {
            return 'Camera permission required. Please enable camera access in Settings > Privacy.';
        }

        // Desktop apps (DesktopAppWindows, DesktopAppMacOS)
        return 'Camera access denied. Please grant camera permission to this application.';
    }

    /**
     * Return WebRTC getUserMedia instructions for browser-based capture.
     *
     * Website platforms (all OS variants) rely on the browser's
     * MediaDevices.getUserMedia() API – no server-side camera API is needed.
     */
    protected function captureViaWebRTC(RuntimePlatform $platform, string $cameraMode): JsonResponse
    {
        return response()->json([
            'camera_mode' => $cameraMode,  // 'webrtc' per cbirCameraMode()
            'platform' => $platform->value,
            'status' => 'instructions',
            'instructions' => 'Use getUserMedia API',
            'hint' => 'Call navigator.mediaDevices.getUserMedia({ video: true }) '
                .'in the browser, capture a frame to a canvas, and POST the image '
                .'as multipart/form-data to /api/cbir/search.',
            'example_js' => 'navigator.mediaDevices.getUserMedia({ video: true })'
                .'.then(stream => { /* capture frame */ });',
        ]);
    }
}
