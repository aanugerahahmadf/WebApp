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
 *  - Hot-module-replacement hot file
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
 * Requirements: 4.1, 4.7
 */

export default defineConfig(async ({ mode }) => {
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

    // The NativePHP Vite plugin is only needed for mobile builds. Loading it
    // lazily keeps ordinary web/desktop builds independent of mobile assets.
    // This matters in CI, where Composer may omit platform-specific resources.
    let nativephpMobile;
    let nativephpHotFile;

    if (activePlatform === 'mobile') {
        const nativephpPluginPath = './vendor/nativephp/mobile/resources/js/' + 'vite-plugin.js';
        ({ nativephpMobile, nativephpHotFile } = await import(nativephpPluginPath));
    }

    // ─── Per-platform build descriptors ──────────────────────────────────
    // Each descriptor declares:
    //   input        — Vite / laravel-vite-plugin entry points
    //   buildDir     — relative path inside public/ (used by laravel-vite-plugin)
    //   publicBuild  — full path from project root (used by build.outDir)
    //   hotFile      — path of the "hot" file used by Laravel's asset() helper
    //   plugins      — platform-specific Vite plugins
    const platformConfigs = {
        web: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-web/app-web.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:    'build/web',
            publicBuild: 'public/build/web',
            hotFile:     'public/hot',
            plugins:     [],
        },
        mobile: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-mobile/app-mobile.js',
                './vendor/nativephp/mobile/resources/js/phpProtocolAdapter.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:    'build/mobile',
            publicBuild: 'public/build/mobile',
            // nativephpHotFile() returns 'public/ios-hot', 'public/android-hot',
            // or 'public/hot' depending on --mode=ios / --mode=android flags.
            hotFile:     activePlatform === 'mobile' ? nativephpHotFile() : 'public/mobile-hot',
            plugins:     activePlatform === 'mobile' ? [nativephpMobile()] : [],
        },
        desktop: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-desktop/app-desktop.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:    'build/desktop',
            publicBuild: 'public/build/desktop',
            hotFile:     'public/hot',
            plugins:     [],
        },
    };

    const config = platformConfigs[activePlatform];

    console.log(`[vite.config] Building for platform: ${activePlatform}`);

    // ─── Vite configuration ───────────────────────────────────────────────
    return {
        plugins: [
            laravel({
                input:            config.input,
                refresh:          true,
                // Each platform writes its own hot file so Laravel's asset()
                // helper can find the dev server for the correct platform.
                hotFile:          config.hotFile,
                // buildDirectory is relative to public/, e.g. "build/web"
                buildDirectory:   config.buildDir,
            }),
            tailwindcss(),
            ...config.plugins,
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
