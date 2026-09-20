# Desktop Application Distribution Guide

This guide covers building and distributing the Laravel Wedding Organizer CBIR application as a native desktop application for Windows and macOS using [NativePHP Electron](https://nativephp.com).

---

## Prerequisites

| Requirement | Details |
|---|---|
| `nativephp/electron` package | `composer require nativephp/electron` |
| `nativephp/laravel` package | `composer require nativephp/laravel` |
| PHP 8.2+ CLI | Required on the build machine |
| Node.js 18+ | Required (Electron toolchain) |
| Electron Builder | Installed automatically by `nativephp/electron` |
| **Windows builds:** Windows 10+ | Cross-compilation from macOS/Linux is limited |
| **macOS builds:** macOS 12+ + Xcode | Required for `.app` packaging and code signing |
| Apple Developer Account | Required for macOS notarization and distribution |
| Windows Code Signing Certificate | Required for Windows Authenticode signing (optional but recommended) |

Install and scaffold NativePHP Desktop once per project:

```bash
composer require nativephp/electron nativephp/laravel
php artisan native:install
```

---

## Environment Configuration

Create `.env.desktop` from the example file:

```bash
cp .env.desktop.example .env.desktop
```

Required variables in `.env.desktop`:

```dotenv
APP_ENV=production
APP_KEY=base64:...           # Generate: php artisan key:generate
APP_URL=http://localhost:8002

# Single-user local file session is ideal for desktop
SESSION_DRIVER=file
SESSION_LIFETIME=10080

# Desktop platform marker
VITE_PLATFORM=desktop

# Embedded PHP server port
NATIVEPHP_HTTP_PORT=8002

# NativePHP app identity
NATIVEPHP_APP_ID=com.yourcompany.weddingorganizer
NATIVEPHP_APP_NAME="Wedding Flower Decorations"
NATIVEPHP_APP_VERSION=1.0.0
```

### Required Variable Reference

| Variable | Required | Description |
|---|---|---|
| `APP_ENV` | Yes | Must be `production` for distributed builds |
| `APP_KEY` | Yes | 32-byte base64 encryption key |
| `APP_URL` | Yes | Should match `http://localhost:{NATIVEPHP_HTTP_PORT}` |
| `SESSION_DRIVER` | Yes | Use `file` for desktop (single-user local) |
| `VITE_PLATFORM` | Yes | Must be `desktop` |
| `NATIVEPHP_HTTP_PORT` | Yes | Port the embedded PHP server listens on (default 8002) |
| `NATIVEPHP_APP_ID` | Yes | Unique reverse-DNS bundle identifier |
| `NATIVEPHP_APP_VERSION` | Yes | Semantic version string (e.g. `1.0.0`) |

---

## Build Steps

### 1. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 2. Compile desktop assets

```bash
npm run build:desktop
```

Assets output to `public/build/desktop/`.

### 3. Cache Laravel configuration

```bash
php artisan optimize
```

### 4. Build the Electron app

```bash
php artisan native:build
```

NativePHP Electron's build command packages the PHP runtime, Laravel application, and Electron shell into a distributable bundle.

---

## Windows Packaging

### Development

Start the app in development mode with live reloading:

```bash
php artisan native:serve
```

### Production `.exe` Installer

```bash
php artisan native:build --os=win
```

Output: `dist/Wedding-Organizer-Setup-{version}.exe` (NSIS installer) and `dist/Wedding-Organizer-{version}-win.zip` (portable).

### Windows Code Signing (Authenticode)

Unsigned Windows installers trigger SmartScreen warnings. Sign with an Authenticode certificate from a trusted CA (DigiCert, Sectigo, etc.):

```dotenv
NATIVEPHP_WINDOWS_CERT_FILE=/path/to/certificate.pfx
NATIVEPHP_WINDOWS_CERT_PASSWORD=your-cert-password
```

Or use a cloud-based HSM signing service (Trusted Signing, SignPath):

```yaml
# In electron-builder config (nativephp.config.js or package.json)
win:
  certificateSubjectName: "Your Company Name"
  signingHashAlgorithms: ["sha256"]
  sign: "./scripts/sign-windows.js"
```

### Windows Build Configuration

Key settings in `config/nativephp.php`:

```php
'app_id'      => env('NATIVEPHP_APP_ID', 'com.yourcompany.weddingorganizer'),
'app_name'    => env('NATIVEPHP_APP_NAME', 'Wedding Flowers Decorasi'),
'version'     => env('NATIVEPHP_APP_VERSION', '1.0.0'),
'windows'     => [
    'target'        => ['nsis', 'portable'],
    'icon'          => 'resources/icons/icon.ico',
    'request_elevation' => false,
],
```

---

## macOS Packaging

### Development

```bash
php artisan native:serve
```

### Production `.dmg` / `.app`

```bash
php artisan native:build --os=mac
```

Output: `dist/Wedding-Organizer-{version}.dmg` and `dist/Wedding-Organizer-{version}-mac.zip`.

### macOS Code Signing and Notarization

macOS requires code signing with an Apple Developer ID certificate. Without it, Gatekeeper blocks the app on first launch. **Notarization** is required for distribution outside the Mac App Store on macOS 10.15+.

Set up credentials:

```dotenv
NATIVEPHP_MACOS_IDENTITY="Developer ID Application: Your Name (TEAMID)"
NATIVEPHP_APPLE_ID=your@apple.com
NATIVEPHP_APPLE_APP_SPECIFIC_PASSWORD=xxxx-xxxx-xxxx-xxxx
NATIVEPHP_APPLE_TEAM_ID=ABCDE12345
```

The build pipeline will automatically:
1. Sign all binaries with your Developer ID certificate.
2. Submit the `.dmg` to Apple's notarization service.
3. Staple the notarization ticket to the `.dmg` so it opens offline.

### macOS Build Configuration

```php
'macos' => [
    'target'              => ['dmg', 'zip'],
    'icon'                => 'resources/icons/icon.icns',
    'minimum_system_version' => '12.0',
    'entitlements'        => 'resources/entitlements.mac.plist',
    'hardened_runtime'    => true,
    'category'            => 'public.app-category.lifestyle',
],
```

Required `entitlements.mac.plist` for camera access:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
  "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>com.apple.security.cs.allow-jit</key>           <true/>
  <key>com.apple.security.cs.allow-unsigned-executable-memory</key> <true/>
  <key>com.apple.security.device.camera</key>           <true/>
  <key>com.apple.security.files.user-selected.read-write</key> <true/>
</dict>
</plist>
```

---

## Cross-Platform Builds

Electron Builder supports cross-compilation, but with limitations:

| Build target | Build machine | Notes |
|---|---|---|
| Windows `.exe` | Windows (recommended) | Also possible on Linux via Wine |
| Windows `.exe` | macOS | Limited — no native NSIS support |
| macOS `.dmg` | macOS only | Apple requires macOS for notarization |
| Linux `.AppImage` | Linux or macOS/Windows via Docker | |

For CI/CD, use platform-specific runners (GitHub Actions `windows-latest`, `macos-latest`) — see the CI/CD pipeline examples.

---

## Distribution Strategies

### Direct Download

Host the installer on your website or S3 bucket. Users download and run it manually. Suitable for internal enterprise distribution.

```
https://downloads.yourcompany.com/wedding-organizer/
  ├── Wedding-Organizer-Setup-1.0.0.exe      ← Windows installer
  ├── Wedding-Organizer-1.0.0.dmg            ← macOS disk image
  └── latest.yml                              ← Auto-updater manifest
```

### Auto-Updates (NativePHP Electron)

NativePHP Electron integrates Electron's `autoUpdater` module. Publish a `latest.yml` (Windows) and `latest-mac.yml` (macOS) alongside your installers, and the app will check for updates on startup.

```dotenv
# URL where NativePHP checks for updates
NATIVEPHP_UPDATER_URL=https://downloads.yourcompany.com/wedding-organizer/
```

Manual update check in PHP:

```php
if (platform_feature('auto_updates')) {
    \Native\Laravel\Facades\Updater::check();
}
```

### Microsoft Store

Build an MSIX package for Microsoft Store distribution:

```bash
php artisan native:build --os=win --target=appx
```

Requires a Microsoft Partner Center account and a code signing certificate trusted by the Store.

### Mac App Store

Mac App Store builds require a separate `Mac App Store Distribution` certificate and use sandboxed entitlements. Consult the [NativePHP documentation](https://nativephp.com) for MAS-specific configuration.

---

## Post-Build Verification

After building, verify the package before distribution:

```bash
# Confirm desktop asset manifest exists
ls -la public/build/desktop/manifest.json

# Inspect the built app bundle (macOS)
codesign -dvv dist/Wedding-Organizer-1.0.0.dmg

# Verify notarization (macOS)
spctl --assess --type open --context context:primary-signature -v dist/Wedding-Organizer-1.0.0.dmg

# Run the installer on a clean machine to verify it works without development dependencies
```

---

## Related Documentation

- [Asset Compilation](../asset-compilation.md) — how `npm run build:desktop` works
- [Environment Configuration](../environment-configuration.md) — `.env.desktop` variable reference
- `.env.desktop.example` — annotated starter file
- [CI/CD Pipeline Examples](../ci-cd.md) — automated build workflows for all three platforms
