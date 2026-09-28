# Proses Kompilasi Aset

Dokumen ini menjelaskan bagaimana pipeline build Vite dikonfigurasi untuk kompilasi aset multi-platform pada aplikasi Laravel Wedding Organizer CBIR.

---

## Ikhtisar

Proyek ini menggunakan satu file `vite.config.js` yang membaca variabel lingkungan `VITE_PLATFORM` untuk menentukan platform mana yang akan di-build. Setiap platform mendapatkan:

- File JavaScript entry point tersendiri
- Direktori output di dalam `public/build/`
- Manifest aset (`manifest.json`)
- Hot file untuk hot-module-replacement (HMR)

Paket `cross-env` digunakan dalam skrip npm untuk mengatur `VITE_PLATFORM` secara lintas platform (Windows, macOS, Linux).

---

## Pemilihan Platform

Vite membaca platform aktif dari variabel lingkungan `VITE_PLATFORM`. Urutan resolusinya adalah:

1. Lingkungan Shell / CI (`process.env.VITE_PLATFORM`)
2. Nilai file `.env` (`VITE_PLATFORM=...`)
3. Default: `web`

Nilai yang valid adalah `web`, `mobile`, dan `desktop`. Nilai yang tidak dikenal akan memicu peringatan dan kembali ke `web`.

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

Setiap platform memiliki entry point JavaScript khusus di dalam `resources/js/`. Entry point ini berbagi dependensi yang sama, namun berbeda pada API yang spesifik untuk setiap platform.

### `resources/js/app-web/app-web.js`

Digunakan untuk deployment di browser web (`php artisan serve`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- Menggunakan **WebRTC `getUserMedia` API** untuk akses kamera (fitur CBIR)
- **Tidak** mengimpor `phpProtocolAdapter` (khusus mobile)
- Mengekspos `window.__PLATFORM__` dengan `type: 'web'` dan `supportsWebRTC: true`

### `resources/js/app-mobile/app-mobile.js`

Digunakan untuk NativePHP Mobile (Android / iOS) (`php artisan native:run`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- Mengimpor `../phpProtocolAdapter/phpProtocolAdapter` — wajib untuk jembatan protokol `php://` pada iOS
- Menggunakan **NativePHP Mobile Camera API** sebagai pengganti WebRTC
- Mengekspos `window.__PLATFORM__` dengan `type: 'mobile'`, deteksi sub-platform (`isIOS`, `isAndroid`)

### `resources/js/app-desktop/app-desktop.js`

Digunakan untuk NativePHP Electron (Windows / macOS) (`php artisan native:serve`).

- Mengimpor `../bootstrap/bootstrap`, komponen UI bersama, dan Firebase client
- **Tidak** mengimpor `phpProtocolAdapter`
- Menggunakan **NativePHP Electron Camera API** sebagai pengganti WebRTC
- Mengekspos `window.__PLATFORM__` dengan `type: 'desktop'`, deteksi sub-platform (`isWindows`, `isMacOS`)

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

`build:all` menjalankan `build:web && build:mobile && build:desktop` secara berurutan, menghasilkan ketiga direktori output.

#### Build Sub-Platform Mobile

Build mobile dapat menargetkan OS tertentu:

```bash
# Bundle khusus iOS (mengatur --mode=ios)
npm run build:mobile:ios

# Bundle khusus Android (mengatur --mode=android)
npm run build:mobile:android
```

---

## Perintah Pengembangan / HMR

Setiap platform berjalan pada port terpisah sehingga ketiganya dapat aktif secara bersamaan selama pengembangan.

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

HMR host secara default adalah `localhost` dan dapat diganti melalui `VITE_HMR_HOST` — berguna di lingkungan Docker atau WSL:

```bash
VITE_HMR_HOST=0.0.0.0 npm run dev:web
```

### Dukungan HMR Per Platform

Ketiga platform sepenuhnya mendukung hot module replacement Vite selama pengembangan. Perubahan pada file yang dipantau langsung dikirim ke browser / native webview tanpa perlu reload penuh.

File cache Blade view (`storage/framework/views/**`) dikecualikan dari file watcher untuk mengurangi noise HMR yang tidak perlu.

Helper Laravel `asset()` menemukan dev server Vite melalui "hot file":

| Platform | Hot File            |
|----------|---------------------|
| web      | `public/hot`        |
| mobile   | `public/ios-hot` atau `public/android-hot` (diatur oleh `nativephpHotFile()`) |
| desktop  | `public/hot`        |

---

## Direktori Output

Setiap platform menulis aset yang telah dikompilasi ke direktori terpisah di bawah `public/build/`:

| Platform | Direktori Output        | Path Manifest                        |
|----------|-------------------------|--------------------------------------|
| web      | `public/build/web`      | `public/build/web/manifest.json`     |
| mobile   | `public/build/mobile`   | `public/build/mobile/manifest.json`  |
| desktop  | `public/build/desktop`  | `public/build/desktop/manifest.json` |

Kelas `PlatformAssetManager` membaca manifest yang sesuai saat runtime berdasarkan mode platform aktif, sehingga direktif `@vite()` Laravel dan helper `asset()` selalu me-resolve ke nama file yang telah di-hash dari build yang tepat.

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

Berikut adalah ringkasan beranotasi dari konfigurasi lengkap:

```js
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { nativephpMobile, nativephpHotFile } from './vendor/nativephp/mobile/resources/js/vite-plugin.js';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const platform = process.env.VITE_PLATFORM || env.VITE_PLATFORM || 'web';
    const activePlatform = ['web', 'mobile', 'desktop'].includes(platform) ? platform : 'web';

    // Per-platform descriptors
    const platformConfigs = {
        web: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-web/app-web.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir: 'build/web',
            publicBuild: 'public/build/web',
            hotFile: 'public/hot',
            plugins: [],
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
            buildDir: 'build/mobile',
            publicBuild: 'public/build/mobile',
            hotFile: nativephpHotFile(), // 'public/ios-hot' or 'public/android-hot'
            plugins: [nativephpMobile()],
        },
        desktop: {
            input: [
                'resources/css/User/User.css',
                'resources/css/Admin/Admin.css',
                'resources/js/app-desktop/app-desktop.js',
                './vendor/tangodev-it/filament-emoji-picker/resources/js/index.js',
                'resources/js/echo/echo.js',
            ],
            buildDir: 'build/desktop',
            publicBuild: 'public/build/desktop',
            hotFile: 'public/hot',
            plugins: [],
        },
    };

    const config = platformConfigs[activePlatform];

    return {
        plugins: [
            laravel({
                input: config.input,
                refresh: true,
                hotFile: config.hotFile,
                buildDirectory: config.buildDir, // relative to public/
            }),
            tailwindcss(),
            ...config.plugins,
        ],

        // Build-time constants exposed to client JS
        define: {
            __VITE_PLATFORM__:          JSON.stringify(activePlatform),
            __VITE_PLATFORM_WEB__:      JSON.stringify(activePlatform === 'web'),
            __VITE_PLATFORM_MOBILE__:   JSON.stringify(activePlatform === 'mobile'),
            __VITE_PLATFORM_DESKTOP__:  JSON.stringify(activePlatform === 'desktop'),
        },

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

        build: {
            outDir: config.publicBuild,
            manifest: true, // emit manifest.json for Laravel asset() helper
        },
    };
});
```

---

## Menggunakan Konstanta Platform di JavaScript

Konstanta `__VITE_PLATFORM__` dapat digunakan untuk logika kondisional yang di-tree-shake saat build:

```js
// Dead code is removed by Rollup during production builds
if (__VITE_PLATFORM_MOBILE__) {
    // Only included in the mobile bundle
    import('./mobile-only-feature');
}

if (__VITE_PLATFORM__ === 'desktop') {
    // Only included in the desktop bundle
    window.electron = require('electron');
}
```

---

## Perbandingan Fitur Entry Point Per Platform

| Fitur                       | app-web.js | app-mobile.js | app-desktop.js |
|-----------------------------|:----------:|:-------------:|:--------------:|
| Kamera WebRTC               | ✅          | ❌             | ❌              |
| Kamera NativePHP Mobile     | ❌          | ✅             | ❌              |
| Kamera NativePHP Desktop    | ❌          | ❌             | ✅              |
| phpProtocolAdapter (iOS)    | ❌          | ✅             | ❌              |
| Push notifications          | ❌          | ✅             | ❌              |
| Notifikasi desktop          | ❌          | ❌             | ✅              |
| Akses sistem file native    | ❌          | ✅             | ✅              |
| Firebase client             | ✅          | ✅             | ✅              |

---

## Dokumentasi Terkait

- [Ikhtisar Dukungan Platform](./platform-support.md)
- [Konfigurasi Lingkungan](./environment-configuration.md)
- [Matriks Fitur Platform](./platform-features.md)
