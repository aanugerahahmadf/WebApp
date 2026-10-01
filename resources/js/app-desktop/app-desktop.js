/**
 * Desktop Platform Entry Point
 *
 * Bundle for the Capacitor desktop shell (the `electron` folder under
 * app/Capacitor/UserApp and app/Capacitor/AdminApp, Windows + macOS).
 *
 * Same shape as the mobile entry: the shell is a Chromium window pointed at
 * this Laravel server, so there is no PHP in this bundle and no native bridge.
 * Ordinary web APIs — WebRTC included — are available as-is.
 *
 * Built with: npm run build:desktop  (→ public/build/desktop)
 * Served against with: php artisan serve:desktop
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

// No phpProtocolAdapter import: it was only needed by NativePHP's iOS build,
// which served the app over a `php://` scheme.
// No echo.js import either — bootstrap.js already pulls it in.

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
// Consistent shape with app-web.js / app-mobile.js so views can branch on the
// platform without knowing anything about the PHP-side PlatformMode.
window.__PLATFORM__ = {
    type: 'desktop',
    mode: 'desktop',

    // Sub-platform detection
    isWindows: navigator.platform.toLowerCase().includes('win'),
    isMacOS: navigator.platform.toLowerCase().includes('mac'),

    // Feature flags — mirrors PlatformFeatureRegistry on the PHP side.
    supportsWebRTC: true,                 // The shell's renderer is a real browser
    supportsShellCamera: true,            // Camera via hidden file inputs
    supportsFileSystem: true,             // <input type="file"> reaches the OS picker
    supportsPushNotifications: false,     // Push notifications — Mobile only
    supportsDesktopNotifications: true,   // OS notifications, delivered over WebSocket
    supportsAppBadge: false,              // App-badge updates — Mobile only
    supportsAutoUpdates: true,            // electron-updater, in the shell package

    // Camera mode for the CBIR feature. Matches RuntimePlatform::cbirCameraMode(),
    // where 'native' means "let the shell pick the file" rather than "run WebRTC".
    cameraMode: 'native',
};

if (import.meta.env.DEV) {
    const os = window.__PLATFORM__.isMacOS ? 'macOS' : window.__PLATFORM__.isWindows ? 'Windows' : 'unknown';
    console.log('[Desktop Platform] Initialized —', os, '— Capacitor shell');
}
