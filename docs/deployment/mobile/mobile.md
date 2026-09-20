# Mobile App Store Deployment Guide

This guide covers building and publishing the Laravel Wedding Organizer CBIR application for Android (Google Play Store) and iOS (Apple App Store) using [NativePHP Mobile](https://github.com/nativephp/mobile).

---

## Prerequisites

| Requirement | Details |
|---|---|
| `nativephp/mobile` package | `composer require nativephp/mobile` |
| PHP 8.2+ CLI | Required on the build machine |
| Node.js 18+ | Required for asset compilation |
| Android SDK + ADB | Required for Android builds |
| Xcode 15+ | Required for iOS builds (macOS only) |
| Apple Developer Account | Required for iOS App Store submission |
| Google Play Developer Account | Required for Android Play Store submission |

Install and scaffold NativePHP Mobile once per project:

```bash
composer require nativephp/mobile
php artisan native:install
```

---

## Environment Configuration

Create `.env.mobile` from the example file:

```bash
cp .env.mobile.example .env.mobile
```

Required variables in `.env.mobile`:

```dotenv
APP_ENV=production
APP_KEY=base64:...          # Generate: php artisan key:generate
APP_URL=https://your-api.com

# Session — database driver survives app restarts
SESSION_DRIVER=database
SESSION_LIFETIME=10080

# Mobile platform marker
VITE_PLATFORM=mobile

# NativePHP Mobile app identity
NATIVEPHP_APP_ID=com.yourcompany.weddingorganizer
NATIVEPHP_APP_NAME="Wedding Flower Decorations"
NATIVEPHP_APP_VERSION=1.0.0
NATIVEPHP_APP_VERSION_CODE=1
```

### Required Variable Reference

| Variable | Required | Description |
|---|---|---|
| `APP_ENV` | Yes | Must be `production` for store builds |
| `APP_KEY` | Yes | 32-byte base64 encryption key |
| `APP_URL` | Yes | URL of the backend API server |
| `SESSION_DRIVER` | Yes | Use `database` for mobile |
| `VITE_PLATFORM` | Yes | Must be `mobile` |
| `NATIVEPHP_APP_ID` | Yes | Unique reverse-DNS bundle identifier |
| `NATIVEPHP_APP_VERSION` | Yes | Semantic version string (e.g. `1.0.0`) |
| `NATIVEPHP_APP_VERSION_CODE` | Yes | Integer version code, increment on each release |

---

## Build Steps

### 1. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 2. Compile mobile assets

```bash
npm run build:mobile
```

Assets output to `public/build/mobile/`.

### 3. Cache Laravel configuration

```bash
php artisan optimize
```

### 4. Run database migrations (if using a remote DB)

```bash
php artisan migrate --force
```

---

## Android Build

### Development / Debug Build

Deploy directly to a connected device or emulator:

```bash
# Auto-detect connected Android device
php artisan native:run android

# Specify a device by ADB serial
php artisan native:run android <device-serial>

# Watch mode (hot reload during development)
php artisan native:run android --watch
```

### Release Build (APK)

```bash
php artisan native:run android --build=release
```

Output: `build/android/app-release.apk`

### App Bundle (Play Store)

The Play Store requires an Android App Bundle (`.aab`) instead of a plain APK:

```bash
php artisan native:run android --build=bundle
```

Output: `build/android/app-release.aab`

### Android Keystore (Code Signing)

Generate a keystore for signing release builds. **Keep the keystore file and passwords secret — losing them means you cannot update the app.**

```bash
keytool -genkey -v \
  -keystore wedding-organizer-release.jks \
  -alias wedding-organizer \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

Configure signing in `nativephp.php` or pass via environment variables:

```dotenv
NATIVEPHP_ANDROID_KEYSTORE_PATH=/path/to/wedding-organizer-release.jks
NATIVEPHP_ANDROID_KEYSTORE_PASSWORD=your-keystore-password
NATIVEPHP_ANDROID_KEY_ALIAS=wedding-organizer
NATIVEPHP_ANDROID_KEY_PASSWORD=your-key-password
```

### Android Build Configuration

Key settings in `config/nativephp.php` (or equivalent NativePHP config):

```php
'app_id'           => env('NATIVEPHP_APP_ID', 'com.yourcompany.weddingorganizer'),
'app_name'         => env('NATIVEPHP_APP_NAME', 'Wedding Flowers Decorasi'),
'version'          => env('NATIVEPHP_APP_VERSION', '1.0.0'),
'android' => [
    'version_code'     => (int) env('NATIVEPHP_APP_VERSION_CODE', 1),
    'min_sdk_version'  => 24,   // Android 7.0+
    'target_sdk_version' => 34, // Android 14
    'permissions'      => [
        'android.permission.CAMERA',
        'android.permission.READ_EXTERNAL_STORAGE',
        'android.permission.WRITE_EXTERNAL_STORAGE',
        'android.permission.INTERNET',
        'android.permission.POST_NOTIFICATIONS',
    ],
],
```

---

## iOS Build

> **iOS builds require macOS.** You cannot build for iOS on Windows or Linux.

### Development Build

```bash
# Deploy to connected iOS device or simulator
php artisan native:run ios

# Watch mode
php artisan native:run ios --watch
```

### Release Build

```bash
php artisan native:run ios --build=release
```

This produces an `.ipa` archive ready for TestFlight or App Store submission.

### iOS Code Signing

iOS apps must be signed with an Apple Developer certificate and provisioning profile. Set up via Xcode or the command line:

1. Open Xcode → Preferences → Accounts → add your Apple ID.
2. In the project target, set your Team and Bundle Identifier to match `NATIVEPHP_APP_ID`.
3. Enable automatic signing, or manually select the distribution certificate and provisioning profile.

Required environment variables for automated CI signing:

```dotenv
NATIVEPHP_IOS_TEAM_ID=ABCDE12345
NATIVEPHP_IOS_BUNDLE_ID=com.yourcompany.weddingorganizer
NATIVEPHP_IOS_CERTIFICATE_PATH=/path/to/distribution.p12
NATIVEPHP_IOS_CERTIFICATE_PASSWORD=cert-password
NATIVEPHP_IOS_PROVISIONING_PROFILE=/path/to/profile.mobileprovision
```

### iOS Build Configuration

Key settings in `config/nativephp.php`:

```php
'ios' => [
    'deployment_target'   => '16.0',   // iOS 16+
    'device_families'     => ['iphone', 'ipad'],
    'permissions'         => [
        'NSCameraUsageDescription'       => 'Required for CBIR image search.',
        'NSPhotoLibraryUsageDescription' => 'Required to select images for search.',
    ],
],
```

---

## App Store Submission

### Google Play Store

1. Create the app in [Google Play Console](https://play.google.com/console).
2. Set up the app signing key — use Play App Signing for the best key recovery options.
3. Upload the `.aab` bundle to the desired track (Internal → Alpha → Beta → Production).
4. Complete the Store Listing: screenshots, description, content rating, privacy policy URL.
5. Submit for review.

**Release checklist:**

```
☐ APP_ENV=production in .env.mobile
☐ NATIVEPHP_APP_VERSION_CODE incremented
☐ AAB signed with release keystore
☐ Target SDK = 34 (Android 14 — current Play Store requirement)
☐ Privacy policy URL set
☐ At least 2 screenshots per device type
☐ Content rating questionnaire completed
```

### Apple App Store

1. Create the app record in [App Store Connect](https://appstoreconnect.apple.com).
2. Archive the app in Xcode (Product → Archive) or use `native:run ios --build=release`.
3. Upload the `.ipa` to App Store Connect via Xcode Organizer or `xcrun altool`.
4. Complete the App Store listing: screenshots, description, keywords, support URL.
5. Submit for App Review.

**Release checklist:**

```
☐ APP_ENV=production in .env.mobile
☐ Bundle version incremented (CFBundleVersion)
☐ Signed with Distribution certificate + App Store provisioning profile
☐ All required permission usage descriptions filled in Info.plist
☐ Privacy policy URL set
☐ At least 1 screenshot per required device (iPhone 6.5", iPhone 5.5", iPad Pro 12.9")
☐ App Review information: demo account credentials provided
```

---

## Post-Deployment Verification

After rolling out a new release, verify the deployment:

```bash
# Confirm the mobile platform mode is detected correctly
php artisan platform:status

# Verify mobile asset manifest exists
ls -la public/build/mobile/manifest.json

# Smoke-test the backend API from a device
curl https://your-api.com/api/health
```

---

## Rolling Back a Release

- **Google Play:** Halt the rollout from Play Console → Release → Production → Manage rollout → Halt.
- **Apple App Store:** You cannot remove an already-approved version. Prepare a hotfix release and submit for expedited review.

---

## Related Documentation

- [Asset Compilation](../asset-compilation.md) — how `npm run build:mobile` works
- [Environment Configuration](../environment-configuration.md) — `.env.mobile` variable reference
- `.env.mobile.example` — annotated starter file
- [CI/CD Pipeline Examples](./../deployment-ci.md) — automated build workflows
