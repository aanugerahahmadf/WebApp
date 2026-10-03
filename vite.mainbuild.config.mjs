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
                // Welcome.css wajib ada di sini: WelcomePanelProvider merender
                // `@vite('resources/css/Welcome/Welcome.css')`, dan @vite
                // membaca manifest build ini (public/build/manifest.json).
                // Hilang dari input = panel Welcome gagal render dengan
                // "Unable to locate file in Vite manifest".
                'resources/css/Welcome/Welcome.css',
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app/app.js',
                'resources/js/firebase-auth/firebase-auth.js',
                'resources/js/echo/echo.js',
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