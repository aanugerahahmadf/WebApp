/**
 * Mobile Platform Entry Point
 *
 * Optimized for NativePHP Mobile (Android / iOS) with native camera and
 * file-system support.
 * Used when running: php artisan native:run
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
// Mobile-Specific Components (Req 4.5)
// ===================================
// iOS php:// protocol adapter — required for NativePHP Mobile on iOS (Req 6.1)
// This module is NOT imported in the web or desktop entry points.
import '../phpProtocolAdapter/phpProtocolAdapter';

// WebRTC is intentionally excluded — the native NativePHP Mobile Camera API
// is used instead (Req 4.5, 6.1).

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
// Sub-platform detection (Android vs iOS) is done at runtime from the UA
// string because both devices share the same JS bundle.
const ua = window.navigator.userAgent.toLowerCase();
const isIOS = /ipad|iphone|ipod/.test(ua);
const isAndroid = ua.includes('android');

window.__PLATFORM__ = {
    type: 'mobile',
    mode: 'mobile',

    // Sub-platform
    isAndroid,
    isIOS,

    // Feature flags — mirrors PlatformFeatureRegistry (Req 5.1–5.9)
    supportsWebRTC: false,               // Native camera preferred over WebRTC
    supportsNativeCamera: true,          // NativePHP Mobile Camera API (Req 6.1)
    supportsFileSystem: true,            // Native file-system access (Req 5.8)
    supportsPushNotifications: true,     // Mobile push notifications (Req 5.7)
    supportsDesktopNotifications: false, // Desktop notifications — Desktop only
    supportsAppBadge: true,              // App-badge updates (Req 5.7)
    supportsAutoUpdates: true,           // Auto-update via app stores

    // Camera mode for CBIR feature (Req 6.4)
    cameraMode: 'native-mobile',        // Uses NativePHP Mobile Camera API
};

// ===================================
// Conditional: Native Camera Helper (Req 4.5, 6.1)
// ===================================
// Expose a unified camera-open helper that delegates to the NativePHP
// Mobile Camera API when available, with a graceful degradation message.
if (window.__PLATFORM__.supportsNativeCamera) {
    /**
     * Triggers the native mobile camera via the NativePHP Bridge.
     * Falls back with a user-facing error when the bridge is unavailable.
     *
     * @returns {Promise<string>} Base64-encoded image data URI
     */
    window.__PLATFORM__.openCamera = async () => {
        if (window.NativePHP && window.NativePHP.camera) {
            try {
                return await window.NativePHP.camera.capture();
            } catch (err) {
                console.error('[Mobile Platform] Native camera error:', err);
                throw new Error(
                    'Camera permission required. Please enable camera access in ' +
                    'Settings \u203A Privacy.'
                );
            }
        }
        // Bridge not yet injected (e.g., dev browser preview)
        throw new Error(
            'Native camera bridge not available in this context.'
        );
    };
}

if (import.meta.env.DEV) {
    console.log(
        '[Mobile Platform] Initialized —',
        isIOS ? 'iOS' : isAndroid ? 'Android' : 'unknown',
        '— native camera & file system enabled'
    );
}
