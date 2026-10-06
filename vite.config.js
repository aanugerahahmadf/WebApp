import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Multi-Platform Vite Configuration
 *
 * Supports three distinct platform build targets, each with its own:
 *  - Entry points (platform-specific JS, excluding unused APIs)
 *  - Output directory (public/build/web | mobile | desktop)
 *  - Asset manifest (manifest.json inside each build directory)
 *
 * The Capacitor shells in app/Capacitor/* load this server in a WebView, so
 * mobile and desktop are ordinary web bundles — no embedded PHP, no native
 * protocol adapter. See DelegatesToServeCommand for the server-side side of it.
 *
 * Build commands:
 *   npm run build              → web (default)
 *   npm run build:web          → web
 *   npm run build:mobile       → mobile
 *   npm run build:desktop      → desktop
 *
 * Dev / HMR commands:
 *   npm run dev                → web (default)
 *   npm run dev:web            → web
 *   npm run dev:mobile         → mobile
 *   npm run dev:desktop        → desktop
 *
 * The active platform is read from the VITE_PLATFORM env variable.
 * When not set it defaults to 'web'.
 *
 * There is no per-OS build target: Android and iOS load the same mobile bundle,
 * and the difference between them lives in each Capacitor shell's native
 * project, not here.
 *
 * Requirements: 4.1, 4.7
 */

export default defineConfig(({ mode }) => {
    // Load .env variables so VITE_PLATFORM is available even when the
    // variable is declared only in .env (not in process.env at this point).
    const env = loadEnv(mode, process.cwd(), '');

    // ─── Platform selection ───────────────────────────────────────────────
    // Priority: process.env (shell / CI) > .env VITE_PLATFORM > default 'web'
    const platform = process.env.VITE_PLATFORM || env.VITE_PLATFORM || 'web';
    const validPlatforms = ['web', 'mobile', 'desktop'];

    if (!validPlatforms.includes(platform)) {
        console.warn(
            `[vite.config] Unknown VITE_PLATFORM="${platform}". ` +
            `Falling back to "web". Valid values: ${validPlatforms.join(', ')}.`
        );
    }

    const activePlatform = validPlatforms.includes(platform) ? platform : 'web';

    // ─── Entry points ────────────────────────────────────────────────────
    // Setiap modul di bawah resources/js didaftarkan sebagai entry point Vite
    // sendiri, bukan sekadar di-import oleh entry platform. Dengan begitu satu
    // modul pun bisa diminta sendiri dari Blade lewat
    // @vite('resources/js/...') dan akan ketemu di manifest.
    //
    // Ini wajib, bukan hiasan: social-buttons.blade.php memanggil
    // @vite('resources/js/firebase-auth/firebase-auth.js'). Kalau path itu
    // bukan entry point, ViteManifestNotFoundException dilempar dan halaman
    // social-buttons gagal render.
    //
    // Mendaftarkan modul dua kali -- sebagai entry DAN sebagai import dari entry
    // lain -- aman di sini. Rollup hoist modul bersama ke satu chunk sendiri
    // dan browser mengevaluasinya sekali, jadi efek samping (Echo,
    // initializeApp, penugasan window.*) tidak terpicu dua kali.
    const cssEntries = [
        'resources/css/User/User.css',
        'resources/css/Welcome/Welcome.css',
        'resources/css/Admin/Admin.css',
    ];

    const vendorEntries = [
        './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
    ];

    // Satu entry per folder di resources/js.
    const moduleEntries = [
        'resources/js/advanced-file-upload/advanced-file-upload.js',
        'resources/js/ai-scan-coach/ai-scan-coach.js',
        'resources/js/app/app.js',
        'resources/js/bootstrap/bootstrap.js',
        'resources/js/checkout-address/checkout-address.js',
        'resources/js/echo/echo.js',
        'resources/js/emoji-picker/emoji-picker.js',
        'resources/js/firebase/firebase.js',
        'resources/js/firebase-auth/firebase-auth.js',
        'resources/js/firebase-client/firebase-client.js',
        'resources/js/ocr/document-ocr/document-ocr.js',
        'resources/js/ocr/scanner-ui/scanner-ui.js',
        'resources/js/pdf-preview-plugin/pdf-preview-plugin.js',
        'resources/js/sidebar-auto-expand/sidebar-auto-expand.js',
    ];

    // Entry shell per platform.
    const platformEntries = {
        web:     'resources/js/app-web/app-web.js',
        mobile:  'resources/js/app-mobile/app-mobile.js',
        desktop: 'resources/js/app-desktop/app-desktop.js',
    };

    // ─── Per-platform build descriptors ──────────────────────────────────
    // Each descriptor declares:
    //   buildDir     — relative path inside public/ (used by laravel-vite-plugin)
    //   publicBuild  — full path from project root (used by build.outDir)
    //   hotFile      — path of the "hot" file used by Laravel's asset() helper
    //
    // All three targets are plain web bundles now. The Capacitor shells load this
    // server in a WebView rather than bundling any PHP, so mobile and desktop
    // differ only in which entry points they pull in — there is no per-platform
    // Vite plugin, no `php:` protocol adapter, and no per-OS hot file.
    const platformConfigs = {
        web: {
            buildDir:   'build/web',
            publicBuild: 'public/build/web',
            hotFile:    'public/hot',
        },
        mobile: {
            buildDir:   'build/mobile',
            publicBuild: 'public/build/mobile',
            hotFile:    'public/hot',
        },
        desktop: {
            buildDir:   'build/desktop',
            publicBuild: 'public/build/desktop',
            hotFile:    'public/hot',
        },
    };

    const config = platformConfigs[activePlatform];

    const input = [
        ...cssEntries,
        platformEntries[activePlatform],
        ...vendorEntries,
        ...moduleEntries,
    ];

    console.log(
        `[vite.config] Building for platform: ${activePlatform} ` +
        `(${input.length} entries)`
    );

    // ─── Vite configuration ───────────────────────────────────────────────
    return {
        plugins: [
            laravel({
                input,
                refresh:          true,
                hotFile:          config.hotFile,
                // buildDirectory is relative to public/, e.g. "build/web"
                buildDirectory:   config.buildDir,
            }),
            tailwindcss(),
        ],

        // ── Define: expose platform to client-side JS ─────────────────────
        // These constants are replaced at build-time (tree-shakeable).
        // During `vite dev` they reflect the currently running platform so
        // HMR and conditional imports work correctly (Req 4.7).
        define: {
            // String constant, e.g. "web" | "mobile" | "desktop"
            __VITE_PLATFORM__:         JSON.stringify(activePlatform),
            __VITE_PLATFORM_WEB__:     JSON.stringify(activePlatform === 'web'),
            __VITE_PLATFORM_MOBILE__:  JSON.stringify(activePlatform === 'mobile'),
            __VITE_PLATFORM_DESKTOP__: JSON.stringify(activePlatform === 'desktop'),
        },

        // ── Dev server ────────────────────────────────────────────────────
        server: {
            host: '0.0.0.0',
            // Allow each platform to run on a separate port for simultaneous
            // development (Req 8.1, 8.2):
            //   web:     VITE_PORT or 5173
            //   mobile:  VITE_PORT or 5174
            //   desktop: VITE_PORT or 5175
            port: parseInt(
                process.env.VITE_PORT ||
                (activePlatform === 'web'     ? '5173' :
                 activePlatform === 'mobile'  ? '5174' : '5175')
            ),
            hmr: {
                // Allow overriding the HMR host via env (useful in Docker /
                // WSL environments).  Defaults to 'localhost'.
                host: process.env.VITE_HMR_HOST || 'localhost',
            },
            watch: {
                // Ignore Blade view cache to reduce unnecessary HMR noise.
                ignored: ['**/storage/framework/views/**'],
            },
        },

        // ── Production build ──────────────────────────────────────────────
        build: {
            // Absolute output path; laravel-vite-plugin also uses buildDirectory
            // (relative to public/) — both must point to the same location.
            outDir:   config.publicBuild,
            // Emit a manifest.json so Laravel / PlatformAssetManager can map
            // entry-point paths to their hashed file names (Req 4.6).
            manifest: true,
            rollupOptions: {
                output: {
                    // Let Rollup decide chunk splitting automatically.
                    manualChunks: undefined,
                },
            },
        },
    };
});
