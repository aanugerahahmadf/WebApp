import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Main (top-level) build — feeds Laravel's default @vite() manifest at
 * public/build/manifest.json. Used by the welcome page and Filament pages.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app/app.js',
                'resources/js/firebase-auth/firebase-auth.js',
                'resources/js/echo/echo.js',
                './vendor/nativephp/mobile/resources/js/phpProtocolAdapter.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
            ],
            buildDirectory: 'build',
        }),
        tailwindcss(),
    ],
    build: {
        outDir: 'public/build',
        // Keep the per-platform sub-builds (web/mobile/desktop) intact.
        emptyOutDir: false,
        manifest: 'manifest.json',
    },
});