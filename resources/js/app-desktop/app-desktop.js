/**
 * Desktop Platform Entry Point
 *
 * Optimized for NativePHP Electron (Windows / macOS) with native desktop
 * camera, file-system, and notification support.
 * Used when running: php artisan native:serve
 *
 * Requirements: 4.1, 4.5
 */

// ===================================
// Core Dependencies (All Platforms)
// ===================================
import '../bootstrap/bootstrap';
import '../advanced-file-upload/advanced-file-upload';
import '../sidebar-auto-expand/sidebar-auto-expand';
import 'emoji-picker-element';
import '../emoji-picker/emoji-picker';
import '../pdf-preview-plugin/pdf-preview-plugin';
import '../firebase-client/firebase-client';

// ===================================
// Desktop-Specific Components (Req 4.5)
// ===================================
// phpProtocolAdapter is NOT imported — it is only needed by the Mobile entry
// point for iOS php:// protocol handling.
//
// WebRTC is intentionally excluded — the NativePHP Electron Camera API
// is used instead (Req 4.5, 6.2).
//
// echo.js is already imported by bootstrap.js so no duplicate import here.

// ===================================
// Dark Mode Synchronization
// ===================================
function syncTheme() {
    const theme = localStorage.getItem('theme');
    const isDark =
        theme === 'dark' ||
        (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
}

syncTheme();
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncTheme);

// ===================================
// Platform Capabilities (Req 4.5, 5.1–5.9)
// ===================================
// Exposes a consistent __PLATFORM__ object so Blade views and Livewire
// components can conditionally render platform-specific UI without requiring
// knowledge of the PHP-side PlatformMode.
window.__PLATFORM__ = {
    type: 'desktop',
    mode: 'desktop',

    // Sub-platform detection from navigator.platform (available in Electron)
    isWindows: navigator.platform.toLowerCase().includes('win'),
    isMacOS: navigator.platform.toLowerCase().includes('mac'),

    // Feature flags — mirrors PlatformFeatureRegistry (Req 5.1–5.9)
    supportsWebRTC: false,               // Native camera preferred over WebRTC
    supportsNativeCamera: true,          // NativePHP Electron Camera API (Req 6.2)
    supportsFileSystem: true,            // Native file-system access (Req 5.8)
    supportsPushNotifications: false,    // Push notifications — Mobile only
    supportsDesktopNotifications: true,  // Desktop system notifications (Req 5.6)
    supportsAppBadge: false,             // App-badge updates — Mobile only
    supportsAutoUpdates: true,           // Auto-update via NativePHP Electron updater

    // Camera mode for CBIR feature (Req 6.4)
    cameraMode: 'native-desktop',       // Uses NativePHP Electron Camera API
};

// ===================================
// Conditional: Native Camera Helper (Req 4.5, 6.2)
// ===================================
// Expose a unified camera-open helper that delegates to the NativePHP
// Electron Camera API when available, with a graceful degradation message.
if (window.__PLATFORM__.supportsNativeCamera) {
    /**
     * Triggers the native desktop camera via the NativePHP Electron Bridge.
     * Falls back with a user-facing error when the bridge is unavailable.
     *
     * @returns {Promise<string>} Base64-encoded image data URI
     */
    window.__PLATFORM__.openCamera = async () => {
        if (window.NativePHP && window.NativePHP.camera) {
            try {
                return await window.NativePHP.camera.capture();
            } catch (err) {
                console.error('[Desktop Platform] Native camera error:', err);
                throw new Error(
                    'Camera access denied. Please grant camera permission to this application.'
                );
            }
        }
        // Electron bridge not yet injected (e.g., dev browser preview)
        throw new Error(
            'Native camera bridge not available in this context.'
        );
    };
}

if (import.meta.env.DEV) {
    const os = window.__PLATFORM__.isMacOS ? 'macOS' : window.__PLATFORM__.isWindows ? 'Windows' : 'unknown';
    console.log(
        '[Desktop Platform] Initialized —',
        os,
        '— native camera, file system & desktop notifications enabled'
    );
}
