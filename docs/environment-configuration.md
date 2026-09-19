# Environment Configuration Strategy

This document explains how the application manages environment variables across its three platform modes: **web**, **mobile**, and **desktop**. Understanding this strategy is essential for configuring the app correctly during development and production.

---

## Overview

The application uses a base `.env` file supplemented by **optional** platform-specific override files. When a platform-specific file exists, its values take precedence over any matching keys in `.env`. When no platform-specific file exists, the app runs fine using only `.env`.

```
.env                  ← always loaded (required)
.env.web              ← loaded on top of .env when running in Web mode   (optional)
.env.mobile           ← loaded on top of .env when running in Mobile mode (optional)
.env.desktop          ← loaded on top of .env when running in Desktop mode (optional)
```

The `EnvironmentManager` handles this merging during application bootstrap, before routes are registered or services are resolved.

---

## File Structure and Purpose

### `.env` — Base Configuration (Required)

The base `.env` file contains settings shared across all platforms: database credentials, mail configuration, third-party API keys, and default values for session, cache, and queue drivers.

```dotenv
APP_NAME="Wedding Flower Decorations"
APP_ENV=local
APP_URL=http://127.0.0.1:8000
APP_PORT=8000

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=file
QUEUE_CONNECTION=sync

VITE_APP_NAME="${APP_NAME}"

# ... database, mail, Firebase, Midtrans, etc.
```

All three platform modes inherit every variable from `.env`. You only need the platform-specific files when you want to change a value for a particular mode.

---

### `.env.web` — Web Platform Overrides (Optional)

Loaded when the application starts with `php artisan serve`. Typically used to set the correct `APP_URL` and a browser-friendly session driver.

Copy the example to get started:

```bash
cp .env.web.example .env.web
```

Typical contents:

```dotenv
APP_PORT=8000
APP_URL=http://localhost:8000

SESSION_DRIVER=cookie
SESSION_LIFETIME=120

CACHE_DRIVER=file
QUEUE_CONNECTION=sync

FILESYSTEM_DISK=public

VITE_PLATFORM=web

SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000
```

---

### `.env.mobile` — Mobile Platform Overrides (Optional)

Loaded when the application starts with `php artisan native:run`. Configures the backend for the Android/iOS native app, including the correct host address for the emulator and a database-backed session driver that survives app restarts.

Copy the example to get started:

```bash
cp .env.mobile.example .env.mobile
```

Typical contents:

```dotenv
APP_PORT=8001
NATIVE_SERVER_PORT=8001

# 10.0.2.2 is the Android emulator's alias for the host machine.
# For physical devices, use your LAN IP: http://192.168.1.x:8001
APP_URL=http://10.0.2.2:8001

SESSION_DRIVER=database
SESSION_LIFETIME=10080     # 7 days — reduces mobile re-login friction

CACHE_DRIVER=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

VITE_PLATFORM=mobile
```

---

### `.env.desktop` — Desktop Platform Overrides (Optional)

Loaded when the application starts with `php artisan native:serve`. Configures the embedded NativePHP HTTP server port and uses file-based sessions, which are appropriate for a single-user local application.

Copy the example to get started:

```bash
cp .env.desktop.example .env.desktop
```

Typical contents:

```dotenv
APP_PORT=8002
NATIVEPHP_HTTP_PORT=8002
APP_URL=http://localhost:8002

SESSION_DRIVER=file
SESSION_LIFETIME=43200     # 30 days — desktop apps rarely need re-login

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

VITE_PLATFORM=desktop
```

---

## Merge Precedence: Platform-Specific Overrides Base

When a platform-specific file is present, its values override any matching keys from `.env`. Variables that only exist in `.env` are inherited as-is.

**Example:**

`.env` (base):
```dotenv
SESSION_DRIVER=database
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
```

`.env.web` (platform override):
```dotenv
SESSION_DRIVER=cookie
APP_URL=http://localhost:8000
```

Effective environment when running in Web mode:
```dotenv
SESSION_DRIVER=cookie          ← from .env.web (overrides .env)
APP_URL=http://localhost:8000  ← from .env.web (overrides .env)
DB_HOST=127.0.0.1              ← from .env (no override, inherited)
```

The merge happens at the PHP runtime level by writing to `$_ENV`, `$_SERVER`, and `putenv()`. Laravel's `env()` helper and `config()` calls both pick up the merged values.

---

## Platform Files Are Optional

The application works perfectly with just `.env`. Platform-specific files are only needed when a variable must differ between modes.

| Scenario | Files Needed |
|----------|--------------|
| Only running the web server | `.env` only |
| Running web + mobile simultaneously | `.env`, `.env.mobile` |
| All three platforms simultaneously | `.env`, `.env.web`, `.env.mobile`, `.env.desktop` |
| Running in production (single platform) | `.env` only (or one platform file) |

If a platform file is missing, the `EnvironmentManager` logs a debug message and continues without error — nothing breaks.

---

## EnvironmentManager Loading Behavior

The `EnvironmentManager` class (`app/Support/Platform/EnvironmentManager.php`) is responsible for loading and merging platform environment files. It is invoked by `PlatformModeServiceProvider` during application boot, before routes or views are resolved.

### Load sequence

1. `PlatformModeServiceProvider::boot()` detects the active `PlatformMode` (Web, Mobile, or Desktop).
2. It calls `EnvironmentManager::loadPlatformEnvironment(PlatformMode $mode)`.
3. The method resolves the file path using `PlatformMode::environmentFile()`, e.g. `.env.mobile`.
4. If the file does not exist, it logs a `debug` message and returns early — no error is thrown.
5. If the file exists, it parses each `KEY=VALUE` line, skipping blank lines and `#` comments.
6. Each variable is written to `$_ENV`, `$_SERVER`, and `putenv()`, overwriting any base value for that key.
7. After merging, it logs an `info` message with the file name, variable count, and number of conflicts resolved.

### Parsing rules

- Lines starting with `#` are treated as comments and skipped.
- Empty lines are skipped.
- Values wrapped in `"double"` or `'single'` quotes have the quotes stripped.
- `null`, `(null)`, `empty`, and `(empty)` are normalised to an empty string `""`.
- The first `=` on a line separates the key from the value — values may contain `=`.

### Logging output (local environment)

```
[info]  Loaded platform environment  {"file":".env.mobile","mode":"mobile","vars_count":12,"conflicts_resolved":3}
```

When no platform file is found:

```
[debug] Platform environment file not found, using base environment  {"file":".env.mobile","mode":"mobile"}
```

---

## Common Configuration Scenarios

### Scenario 1: Different `SESSION_DRIVER` per platform

The session driver typically differs across platforms:

| Platform | Recommended Driver | Reason |
|----------|--------------------|--------|
| Web | `cookie` | Stateless HTTP; cookies work natively in browsers |
| Mobile | `database` | Persists across native app restarts; works with token auth |
| Desktop | `file` | Single-user local app; file sessions are fast and simple |

Base `.env`:
```dotenv
SESSION_DRIVER=database
```

`.env.web`:
```dotenv
SESSION_DRIVER=cookie
```

`.env.desktop`:
```dotenv
SESSION_DRIVER=file
```

Mobile mode inherits `SESSION_DRIVER=database` from `.env` — no override needed.

---

### Scenario 2: Different `APP_URL` per platform

Each platform typically runs on a different port (or host) to allow simultaneous development:

`.env` (base):
```dotenv
APP_URL=http://127.0.0.1:8000
```

`.env.web`:
```dotenv
APP_URL=http://localhost:8000
```

`.env.mobile`:
```dotenv
# Android emulator routes 10.0.2.2 to the host machine
APP_URL=http://10.0.2.2:8001

# For physical Android device on the same Wi-Fi network:
# APP_URL=http://192.168.1.42:8001
```

`.env.desktop`:
```dotenv
APP_URL=http://localhost:8002
```

---

### Scenario 3: `VITE_PLATFORM` for frontend asset selection

The Vite build pipeline uses `VITE_PLATFORM` to select the correct JavaScript entry point and asset bundle. Set it in each platform file so the browser/native app loads the right bundle.

`.env.web`:
```dotenv
VITE_PLATFORM=web
```

`.env.mobile`:
```dotenv
VITE_PLATFORM=mobile
```

`.env.desktop`:
```dotenv
VITE_PLATFORM=desktop
```

In Blade templates or JavaScript, you can read this value:

```js
// resources/js/app/app.js
const platform = import.meta.env.VITE_PLATFORM ?? 'web';
```

---

### Scenario 4: Running all three platforms simultaneously

Start each platform in a separate terminal. Each reads its own port from the platform-specific file:

```bash
# Terminal 1 — Web (port 8000)
php artisan serve --port=8000

# Terminal 2 — Mobile (port 8001, set in .env.mobile)
php artisan native:run

# Terminal 3 — Desktop (port 8002, set in .env.desktop)
php artisan native:serve
```

Port assignment convention:

| Platform | Command | Port |
|----------|---------|------|
| Web | `php artisan serve` | 8000 |
| Mobile | `php artisan native:run` | 8001 |
| Desktop | `php artisan native:serve` | 8002 |

---

### Scenario 5: Keeping shared secrets in `.env` only

Secrets such as database passwords, API keys, and mail credentials only need to appear once in `.env`. Platform files never need to duplicate them — they are inherited automatically.

```dotenv
# .env — the single source of truth for secrets
DB_HOST=127.0.0.1
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=root
DB_PASSWORD=secret

MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json
```

Platform files only override the variables that genuinely differ per platform.

---

## Quick Reference

```
.env                   → always loaded; all shared config and secrets go here
.env.web               → (optional) web overrides; copy from .env.web.example
.env.mobile            → (optional) mobile overrides; copy from .env.mobile.example
.env.desktop           → (optional) desktop overrides; copy from .env.desktop.example

Merge rule:            platform file wins over .env for matching keys
Missing platform file: app continues with base .env — no error
Load timing:           during PlatformModeServiceProvider::boot(), before routes
```

---

## See Also

- `app/Support/Platform/EnvironmentManager.php` — implementation
- `app/Providers/PlatformModeServiceProvider.php` — where loading is triggered
- `app/Enums/PlatformMode.php` — `environmentFile()` method returns the file name per mode
- `.env.web.example`, `.env.mobile.example`, `.env.desktop.example` — annotated starter files
- `docs/platform-support.md` — platform architecture overview
