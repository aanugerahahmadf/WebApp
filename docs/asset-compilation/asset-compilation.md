# Asset Compilation Process

This document explains how the Vite build pipeline is configured for multi-platform asset compilation in the Laravel Wedding Organizer CBIR application.

---

## Overview

The project uses a single `vite.config.js` that reads the `VITE_PLATFORM` environment variable to determine which platform to build for. Each platform gets its own:

- Entry point JavaScript file
- Output directory inside `public/build/`
- Asset manifest (`manifest.json`)
- Hot-module-replacement (HMR) hot file

The `cross-env` package is used in npm scripts to set `VITE_PLATFORM` cross-platform (Windows, macOS, Linux).

---

## Platform Selection

Vite reads the active platform from the `VITE_PLATFORM` environment variable. The resolution order is:

1. Shell / CI environment (`process.env.VITE_PLATFORM`)
2. `.env` file value (`VITE_PLATFORM=...`)
3. Default: `web`

Valid values are `web`, `mobile`, and `desktop`. An unknown value triggers a warning and falls back to `web`.

```js
// vite.config.js (simplified)
const platform = process.env.VITE_PLATFORM || env.VITE_PLATFORM || 'web';
const validPlatforms = ['web', 'mobile', 'desktop'];
const activePlatform = validPlatforms.includes(platform) ? platform : 'web';
```

At build time, four constants are injected into the client bundle and tree-shaken:

| Constant                  | Type    | Example (`mobile` build) |
|---------------------------|---------|--------------------------|
| `__VITE_PLATFORM__`       | string  | `"mobile"`               |
| `__VITE_PLATFORM_WEB__`   | boolean | `false`                  |
| `__VITE_PLATFORM_MOBILE__`| boolean | `true`                   |
| `__VITE_PLATFORM_DESKTOP__`| boolean| `false`                  |

---

## Platform-Specific Entry Points

Each platform has a dedicated JavaScript entry point in `resources/js/`. The entry points share common dependencies but diverge on platform-specific APIs.

### `resources/js/app-web/app-web.js`

Used for web browser deployments (`php artisan serve`).

- Imports `../bootstrap/bootstrap`, shared UI components, and Firebase client
- Uses the **WebRTC `getUserMedia` API** for camera access (CBIR feature)
- Does **not** import `phpProtocolAdapter` (mobile-only)
- Exposes `window.__PLATFORM__` with `type: 'web'` and `supportsWebRTC: true`

### `resources/js/app-mobile/app-mobile.js`

Used for NativePHP Mobile (Android / iOS) (`php artisan native:run`).

- Imports `../bootstrap/bootstrap`, shared UI components, and Firebase client
- Imports `../phpProtocolAdapter/phpProtocolAdapter` — required for the iOS `php://` protocol bridge
- Uses the **NativePHP Mobile Camera API** instead of WebRTC
- Exposes `window.__PLATFORM__` with `type: 'mobile'`, sub-platform detection (`isIOS`, `isAndroid`)

### `resources/js/app-desktop/app-desktop.js`

Used for NativePHP Electron (Windows / macOS) (`php artisan native:serve`).

- Imports `../bootstrap/bootstrap`, shared UI components, and Firebase client
- Does **not** import `phpProtocolAdapter`
- Uses the **NativePHP Electron Camera API** instead of WebRTC
- Exposes `window.__PLATFORM__` with `type: 'desktop'`, sub-platform detection (`isWindows`, `isMacOS`)

---

## Build Commands

### Production Builds

Build assets for a specific platform using the `build:*` scripts:

```bash
# Build for web only
npm run build:web

# Build for mobile only
npm run build:mobile

# Build for desktop only
npm run build:desktop

# Build all three platforms sequentially
npm run build:all
```

`build:all` runs `build:web && build:mobile && build:desktop` in sequence, producing all three output directories.

#### Mobile Sub-Platform Builds

Mobile builds can target a specific OS:

```bash
# iOS-specific bundle (sets --mode=ios)
npm run build:mobile:ios

# Android-specific bundle (sets --mode=android)
npm run build:mobile:android
```

---

## Development / HMR Commands

Each platform runs on a separate port so all three can be active simultaneously during development.

| Command              | Platform | Port  |
|----------------------|----------|-------|
| `npm run dev:web`    | web      | 5173  |
| `npm run dev:mobile` | mobile   | 5174  |
| `npm run dev:desktop`| desktop  | 5175  |

The port is determined automatically from the platform, but can be overridden:

```bash
# Override port via environment variable
VITE_PORT=5200 npm run dev:web
```

The HMR host defaults to `localhost` and can be overridden via `VITE_HMR_HOST` — useful in Docker or WSL environments:

```bash
VITE_HMR_HOST=0.0.0.0 npm run dev:web
```

### HMR Support Per Platform

All three platforms fully support Vite's hot module replacement during development. Changes to watched files are pushed to the browser / native webview without a full reload.

Blade view cache files (`storage/framework/views/**`) are excluded from the file watcher to reduce unnecessary HMR noise.

The Laravel `asset()` helper locates the Vite dev server via a "hot file":

| Platform | Hot File            |
|----------|---------------------|
| web      | `public/hot`        |
| mobile   | `public/ios-hot` or `public/android-hot` (set by `nativephpHotFile()`) |
| desktop  | `public/hot`        |

---

## Output Directories

Each platform writes its compiled assets to a separate directory under `public/build/`:

| Platform | Output Directory        | Manifest Path                        |
|----------|-------------------------|--------------------------------------|
| web      | `public/build/web`      | `public/build/web/manifest.json`     |
| mobile   | `public/build/mobile`   | `public/build/mobile/manifest.json`  |
| desktop  | `public/build/desktop`  | `public/build/desktop/manifest.json` |

The `PlatformAssetManager` class reads the correct manifest at runtime based on the active platform mode, so Laravel's `@vite()` directive and the `asset()` helper always resolve to hashed filenames from the right build.

### Example Manifest Entry

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

## Full `vite.config.js` Reference

Below is an annotated summary of the complete configuration:

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

## Using Platform Constants in JavaScript

The `__VITE_PLATFORM__` constants can be used for conditional logic that is tree-shaken at build time:

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

## Platform Entry Point Feature Comparison

| Feature                    | app-web.js | app-mobile.js | app-desktop.js |
|----------------------------|:----------:|:-------------:|:--------------:|
| WebRTC camera              | ✅          | ❌             | ❌              |
| NativePHP Mobile Camera    | ❌          | ✅             | ❌              |
| NativePHP Desktop Camera   | ❌          | ❌             | ✅              |
| phpProtocolAdapter (iOS)   | ❌          | ✅             | ❌              |
| Push notifications         | ❌          | ✅             | ❌              |
| Desktop notifications      | ❌          | ❌             | ✅              |
| Native file system access  | ❌          | ✅             | ✅              |
| Firebase client            | ✅          | ✅             | ✅              |

---

## Related Documentation

- [Platform Support Overview](./platform-support.md)
- [Environment Configuration](./environment-configuration.md)
- [Platform Feature Matrix](./platform-features.md)
