# Proses Kompilasi Aset (Capacitor)

Dokumen ini menjelaskan bagaimana pipeline build Vite dikonfigurasi untuk
kompilasi aset multi-platform pada aplikasi Laravel Wedding Organizer CBIR.

> **Catatan:** Semua referensi ke `nativephp` dihapus. Mobile dan desktop sekarang
> adalah shell Capacitor biasa — tidak ada `phpProtocolAdapter`, tidak ada
> `nativephpMobile` plugin, tidak ada hot file per-OS. Ketiga platform adalah
> bundle web standar.

---

## Ikhtisar

Proyek ini menggunakan satu file `vite.config.js` yang membaca variabel
lingkungan `VITE_PLATFORM` untuk menentukan platform mana yang akan di-build.
Setiap platform mendapatkan:

- File JavaScript entry point tersendiri
- Direktori output di dalam `public/build/`
- Manifest aset (`manifest.json`)
- Hot file untuk hot-module-replacement (HMR) — sama `public/hot` untuk semua

Paket `cross-env` digunakan dalam skrip npm untuk mengatur `VITE_PLATFORM`
secara lintas platform (Windows, macOS, Linux).

---

## Pemilihan Platform

Vite membaca platform aktif dari variabel lingkungan `VITE_PLATFORM`. Urutan
resolusinya adalah:

1. Lingkungan Shell / CI (`process.env.VITE_PLATFORM`)
2. Nilai file `.env` (`VITE_PLATFORM=...`)
3. Default: `web`

Nilai yang valid adalah `web`, `mobile`, dan `desktop`. Nilai yang tidak
dikenal akan memicu peringatan dan kembali ke `web`.

```js
// vite.config.js (simplified)
const platform = process.env.VITE_PLATFORM || env.VITE_PLATFORM || 'web';
const validPlatforms = ['web', 'mobile', 'desktop'];
const activePlatform = validPlatforms.includes(platform) ? platform : 'web';
```

Saat build, empat konstanta disuntikkan ke dalam bundle klien dan di-tree-shake:

| Konstanta                   | Tipe    | Contoh (build `mobile`)  |
|-----------------------------|---------|--------------------------|
| `__VITE_PLATFORM__`         | string  | `"mobile"`               |
| `__VITE_PLATFORM_WEB__`     | boolean | `false`                  |
| `__VITE_PLATFORM_MOBILE__`  | boolean | `true`                   |
| `__VITE_PLATFORM_DESKTOP__` | boolean | `false`                  |

---

## Entry Point Per Platform

Setiap platform memiliki entry point JavaScript khusus di dalam `resources/js/`.
Entry point ini berbagi dependensi yang sama, namun berbeda pada API yang
spesifik untuk setiap platform.

### `resources/js/app-web/app-web.js`

Digunakan untuk browser web (`php artisan serve:web`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- Menggunakan **WebRTC `getUserMedia` API** untuk akses kamera (fitur CBIR)
- Mengekspos `window.__PLATFORM__` dengan:
  - `type: 'web'`
  - `supportsWebRTC: true`
  - `supportsShellCamera: false`

### `resources/js/app-mobile/app-mobile.js`

Digunakan untuk shell Capacitor mobile (`php artisan serve:mobile`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- **Tidak** mengimpor `phpProtocolAdapter` (dihapus)
- Menggunakan **shell Capacitor** untuk kamera: server dispatch event Alpine
  `capacitor-camera-open-input` → shell membuka `<input type="file" capture>`
- Mengekspos `window.__PLATFORM__` dengan:
  - `type: 'mobile'`
  - `supportsWebRTC: false` (shell production pakai input file; WebRTC hanya jika `https`)
  - `supportsShellCamera: true`
  - Deteksi sub-platform: `isIOS`, `isAndroid` (dari `RuntimePlatform`)

### `resources/js/app-desktop/app-desktop.js`

Digunakan untuk shell Capacitor Electron (`php artisan serve:desktop`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- Menggunakan **shell Capacitor Electron** untuk kamera: sama seperti mobile,
  input file tersembunyi
- Mengekspos `window.__PLATFORM__` dengan:
  - `type: 'desktop'`
  - `supportsWebRTC: false`
  - `supportsShellCamera: true`
  - Deteksi sub-platform: `isWindows`, `isMacOS`

---

## Perintah Build

### Build Produksi

Build aset untuk platform tertentu menggunakan skrip `build:*`:

```bash
# Build untuk web saja
npm run build:web

# Build untuk mobile saja
npm run build:mobile

# Build untuk desktop saja
npm run build:desktop

# Build ketiga platform secara berurutan
npm run build:all
```

`build:all` menjalankan `build:web && build:mobile && build:desktop` secara
berurutan, menghasilkan ketiga direktori output.

> **Tidak ada lagi `build:mobile:ios` / `build:mobile:android`.** Android dan iOS
> memuat bundle `mobile` yang sama; perbedaan OS ditangani di project native
> shell (`capacitor.config.json`, `entitlements`), bukan di Vite.

---

## Perintah Pengembangan / HMR

Setiap platform berjalan pada port terpisah sehingga ketiganya dapat aktif
secara bersamaan selama pengembangan.

| Perintah              | Platform | Port  |
|-----------------------|----------|-------|
| `npm run dev:web`     | web      | 5173  |
| `npm run dev:mobile`  | mobile   | 5174  |
| `npm run dev:desktop` | desktop  | 5175  |

Port ditentukan secara otomatis berdasarkan platform, namun dapat diganti:

```bash
# Ganti port melalui variabel lingkungan
VITE_PORT=5200 npm run dev:web
```

HMR host secara default adalah `localhost` dan dapat diganti melalui
`VITE_HMR_HOST` — berguna di lingkungan Docker atau WSL:

```bash
VITE_HMR_HOST=0.0.0.0 npm run dev:web
```

### Dukungan HMR Per Platform

Ketiga platform sepenuhnya mendukung hot module replacement Vite selama
pengembangan. Perubahan pada file yang dipantau langsung dikirim ke browser /
native webview tanpa perlu reload penuh.

File cache Blade view (`storage/framework/views/**`) dikecualikan dari file
watcher untuk mengurangi noise HMR yang tidak perlu.

Helper Laravel `asset()` menemukan dev server Vite melalui "hot file":
**semua platform menggunakan `public/hot`** (tidak ada lagi hot file per-OS).

---

## Direktori Output

Setiap platform menulis aset yang telah dikompilasi ke direktori terpisah di
bawah `public/build/`:

| Platform | Direktori Output        | Path Manifest                        |
|----------|-------------------------|--------------------------------------|
| web      | `public/build/web`      | `public/build/web/manifest.json`     |
| mobile   | `public/build/mobile`   | `public/build/mobile/manifest.json`  |
| desktop  | `public/build/desktop`  | `public/build/desktop/manifest.json` |

Kelas `PlatformAssetManager` membaca manifest yang sesuai saat runtime berdasarkan
mode platform aktif, sehingga direktif `@vite()` Laravel dan helper `asset()`
selalu me-resolve ke nama file yang telah di-hash dari build yang tepat.

### Contoh Entri Manifest

```json
{
  "resources/js/app-web/app-web.js": {
    "file": "assets/app-web.a1b2c3d4.js",
    "src": "resources/js/app-web/app-web.js",
    "isEntry": true,
    "css": ["assets/app-web.e5f6g7h8.css"]
  }
}
```

---

## Referensi Lengkap `vite.config.js`

Berikut adalah ringkasan beranotasi dari konfigurasi lengkap (versi Capacitor):

```js
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

    // ─── Per-platform build descriptors ──────────────────────────────────
    // Each descriptor declares:
    //   input        — Vite / laravel-vite-plugin entry points
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
            input: [
                'resources/css/User/User.css',
                'resources/css/Welcome/Welcome.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-web/app-web.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:   'build/web',
            publicBuild: 'public/build/web',
            hotFile:    'public/hot',
        },
        mobile: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Welcome/Welcome.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-mobile/app-mobile.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:   'build/mobile',
            publicBuild: 'public/build/mobile',
            hotFile:    'public/hot',
        },
        desktop: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Welcome/Welcome.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-desktop/app-desktop.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir:   'build/desktop',
            publicBuild: 'public/build/desktop',
            hotFile:    'public/hot',
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
                hotFile:          config.hotFile,
                buildDirectory:   config.buildDir, // relative to public/
            }),
            tailwindcss(),
        ],

        // ─── Define: expose platform to client-side JS ─────────────────────
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

        // ─── Dev server ────────────────────────────────────────────────────
        server: {
            host: '0.0.0.0',
            port: parseInt(
                process.env.VITE_PORT ||
                (activePlatform === 'web' ? '5173' : activePlatform === 'mobile' ? '5174' : '5175')
            ),
            hmr: {
                host: process.env.VITE_HMR_HOST || 'localhost',
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },

        // ─── Build output ──────────────────────────────────────────────────
        build: {
            outDir: config.publicBuild,
            manifest: true, // emit manifest.json for Laravel asset() helper
        },
    };
});
```

---

## Menggunakan Konstanta Platform di JavaScript

Konstanta `__VITE_PLATFORM__` dapat digunakan untuk logika kondisional yang
di-tree-shake saat build:

```js
// Dead code is removed by Rollup during production builds
if (__VITE_PLATFORM_MOBILE__) {
    // Only included in the mobile bundle
    import('./mobile-only-feature');
}

if (__VITE_PLATFORM__ === 'desktop') {
    // Only included in the desktop bundle
    // (e.g. electron-specific IPC if ever needed)
}
```

---

## Perbandingan Fitur Entry Point Per Platform (Capacitor)

| Fitur                       | app-web.js | app-mobile.js | app-desktop.js |
|-----------------------------|:----------:|:-------------:|:--------------:|
| Kamera WebRTC               | ✅          | ❌             | ❌              |
| Kamera Shell (input file)   | ❌          | ✅             | ✅              |
| Push notifications (FCM)    | ❌          | ✅             | ❌              |
| Desktop Notifications (Web Notif API) | ❌ | ❌ | ✅ |
| Auto-updater (electron-updater) | ❌       | ❌             | ✅ (di shell)   |
| Akses file via input        | ❌          | ✅             | ✅              |
| Firebase client             | ✅          | ✅             | ✅              |
| WebSocket Broadcast (Laravel Echo) | ✅    | ✅             | ✅              |

---

## Dokumentasi Terkait

- [Ikhtisar Dukungan Platform](./platform-support.md)
- [Konfigurasi Lingkungan](./environment-configuration.md)
- [Matriks Fitur Platform](./platform-features.md)
- [Panduan Deployment Mobile](../deployment/mobile/mobile.md)
- [Panduan Deployment Desktop](../deployment/desktop/desktop.md)