/**
 * Web Platform Entry Point
 *
 * Optimized for browser environments with WebRTC support.
 * Built with: npm run build:web   (→ public/build/web)
 * Served against with: php artisan serve:web
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
// Scan Modal OCR Utilities
// ===================================
// Exposed on window so the Alpine-based document / face scan modals can invoke
// client-side OCR without needing a network round-trip.
import * as ocr from '../ocr/document-ocr/document-ocr';
import * as scannerUi from '../ocr/scanner-ui/scanner-ui';

window.OCR = ocr;
window.ScannerUI = scannerUi;

// ===================================
// Web-Specific Components
// ===================================
// The web build is the reference: it uses WebRTC getUserMedia for camera access.
// The Capacitor shell bundles (app-mobile.js / app-desktop.js) deliberately
// deviate, opening a hidden `<input type="file" capture>` instead.

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
// Platform Capabilities
// ===================================
// Consistent shape with app-mobile.js / app-desktop.js so views can branch on
// the platform without knowing anything about the PHP-side PlatformMode.
window.__PLATFORM__ = {
    type: 'web',
    mode: 'web',

    // Feature flags — mirrors PlatformFeatureRegistry on the PHP side.
    supportsWebRTC: true,                 // Browser WebRTC getUserMedia
    supportsShellCamera: false,           // Shell-driven camera — app builds only
    supportsFileSystem: false,            // OS file access via <input type="file">
    supportsPushNotifications: false,     // Push notifications — Mobile only
    supportsDesktopNotifications: false,  // Desktop notifications — Desktop only
    supportsAppBadge: false,              // App-badge updates — Mobile only
    supportsAutoUpdates: false,           // Auto-update functionality — app builds only

    // Camera mode for the CBIR feature. Matches RuntimePlatform::cbirCameraMode().
    cameraMode: 'webrtc',
};

// ===================================
// Conditional: WebRTC Camera Helper
// ===================================
// Lazy-load WebRTC utilities only when the browser supports them.
if (window.__PLATFORM__.supportsWebRTC && navigator.mediaDevices) {
    /**
     * Opens the user's camera and returns a MediaStream.
     * Used by the CBIR browse modal for web-based image capture.
     *
     * @param {MediaStreamConstraints} constraints
     * @returns {Promise<MediaStream>}
     */
    window.__PLATFORM__.openCamera = async (constraints = { video: true }) => {
        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            console.error('[Web Platform] Camera access denied:', err);
            throw new Error(
                'Camera access denied. Please allow camera access in your browser settings.'
            );
        }
    };
}

if (import.meta.env.DEV) {
    console.log('[Web Platform] Initialized — WebRTC camera support enabled');
}
