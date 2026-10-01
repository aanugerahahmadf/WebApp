/**
 * Mobile Platform Entry Point
 *
 * Bundle for the Capacitor mobile shell (app/Capacitor/UserApp and
 * app/Capacitor/AdminApp, Android + iOS).
 *
 * There is no PHP in this bundle and no native bridge. The shell is a WebView
 * pointed at this Laravel server (see each shell's `capacitor.config.json`
 * `server.url`), so every web feature works exactly as it does in a mobile
 * browser — including WebRTC, once the page is served over https or from
 * localhost, both of which the shell provides as a secure context.
 *
 * Camera capture is the one deliberate difference from the web bundle: instead
 * of the in-page WebRTC viewfinder, the shell opens a hidden
 * `<input type="file" capture>` and the upload arrives through `wire:model`.
 * The server side of that handshake is the `capacitor-camera-open-input`
 * dispatch, handled by the `cbir-camera-options` partial under
 * resources/views/{User,Welcome}/components/.
 *
 * Built with: npm run build:mobile   (→ public/build/mobile)
 * Served against with: php artisan serve:mobile
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

// No phpProtocolAdapter import: that existed only for NativePHP's iOS build,
// which served the app over a `php://` scheme. This shell uses plain http(s).

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
// Android vs iOS is read at runtime from the UA string, because both platforms
// share this one bundle.
const ua = window.navigator.userAgent.toLowerCase();
const isIOS = /ipad|iphone|ipod/.test(ua);
const isAndroid = ua.includes('android');

window.__PLATFORM__ = {
    type: 'mobile',
    mode: 'mobile',

    // Sub-platform
    isAndroid,
    isIOS,

    // Feature flags — mirrors PlatformFeatureRegistry on the PHP side.
    supportsWebRTC: true,                 // The shell's WebView is a real browser
    supportsShellCamera: true,            // Camera via hidden file inputs
    supportsFileSystem: true,             // <input type="file"> reaches the OS picker
    supportsPushNotifications: true,      // FCM, via the registered device token
    supportsDesktopNotifications: false,  // Desktop notifications — Desktop only
    supportsAppBadge: true,
    supportsAutoUpdates: true,            // Updated through the app stores

    // Camera mode for the CBIR feature. Matches RuntimePlatform::cbirCameraMode(),
    // where 'native' means "let the shell pick the file" rather than "run WebRTC".
    cameraMode: 'native',
};

if (import.meta.env.DEV) {
    console.log(
        '[Mobile Platform] Initialized —',
        isIOS ? 'iOS' : isAndroid ? 'Android' : 'unknown',
        '— Capacitor shell, shell-driven camera'
    );
}
