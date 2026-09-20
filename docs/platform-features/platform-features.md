# Platform Feature Matrix

This document describes which application features are available on each of the eight supported
runtime platforms, explains what each feature enables in the Wedding Organizer CBIR app, and
shows the three ways you can check feature availability from PHP code.

---

## Feature Matrix

The application defines seven named features. Each is tracked by `PlatformFeatureRegistry` and
mapped to the `RuntimePlatform` cases that support it.

| Feature | Website<br>Windows | Website<br>macOS | Website<br>Android | Website<br>iOS | Mobile App<br>Android | Mobile App<br>iOS | Desktop App<br>Windows | Desktop App<br>macOS |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `camera` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| `webrtc` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `file_system` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| `desktop_notifications` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| `push_notifications` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `auto_updates` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| `app_badge` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |

> **Legend:** ✅ Available &nbsp;|&nbsp; ❌ Not available

### Platform column reference

| Column label | `RuntimePlatform` case | Launched by |
|---|---|---|
| Website Windows | `WebsiteWindows` | `php artisan serve` on Windows/Linux browser |
| Website macOS | `WebsiteMacOS` | `php artisan serve` on macOS browser |
| Website Android | `WebsiteAndroid` | `php artisan serve` on Android browser |
| Website iOS | `WebsiteIos` | `php artisan serve` on iPhone/iPad browser |
| Mobile App Android | `MobileAppAndroid` | `php artisan native:run` on Android device |
| Mobile App iOS | `MobileAppIos` | `php artisan native:run` on iOS device |
| Desktop App Windows | `DesktopAppWindows` | `php artisan native:serve` on Windows |
| Desktop App macOS | `DesktopAppMacOS` | `php artisan native:serve` on macOS |

---

## What Each Feature Enables

### `camera`

**Available on:** Mobile apps, Desktop apps

Grants access to the native device camera through the NativePHP APIs. Used by the
Content-Based Image Retrieval (CBIR) feature to let users photograph garments and search for
visually similar wedding attire without leaving the app.

- Mobile: opens the NativePHP Mobile Camera sheet (`native:run`)
- Desktop: opens the NativePHP Electron Camera dialog (`native:serve`)
- The enum method `$platform->cbirCameraMode()` returns `'native'` for these platforms

### `webrtc`

**Available on:** All website platforms (Windows, macOS, Android, iOS browsers)

Enables camera input via the browser's `MediaDevices.getUserMedia()` API (WebRTC). This is the
web-browser equivalent of the native `camera` feature and powers the CBIR image capture flow
on the website.

- The enum method `$platform->cbirCameraMode()` returns `'webrtc'` for website platforms
- `camera` and `webrtc` are mutually exclusive — a platform has one or the other, never both

### `file_system`

**Available on:** Mobile apps, Desktop apps

Provides read/write access to the device file system through NativePHP APIs. Used to:

- Save captured CBIR reference images locally for offline comparison
- Export order summaries and decoration proposals to PDF files
- Cache downloaded wedding package assets for offline viewing

Website platforms do not have persistent file system access; the browser's `File System Access
API` is not used by this app.

### `desktop_notifications`

**Available on:** Desktop apps only (Windows, macOS)

Sends OS-level desktop notifications via the NativePHP Electron notification API. Used by
`PlatformNotificationService` to alert the organizer about:

- New customer orders
- Payment status changes (via the Midtrans webhook)
- Upcoming scheduled events

Mobile apps use `push_notifications` instead. Website platforms rely on in-app Filament
notifications only.

### `push_notifications`

**Available on:** Mobile apps only (Android, iOS)

Delivers push notifications to the user's device even when the app is backgrounded. Used by
`PlatformNotificationService` for the same organizer-alert use cases as `desktop_notifications`,
but through the mobile platform's push infrastructure (FCM for Android, APNs for iOS).

### `auto_updates`

**Available on:** Desktop apps only (Windows, macOS)

Allows the Electron shell to download and install application updates automatically in the
background. This keeps installed desktop clients current without requiring manual reinstallation
and is handled by the NativePHP Electron auto-updater.

Not applicable to mobile apps (managed by the app stores) or websites (always served fresh).

### `app_badge`

**Available on:** Mobile apps only (Android, iOS)

Sets the numeric badge count on the app icon on the device's home screen. Used to surface
unread message counts or pending order notifications at a glance, without requiring the user to
open the app.

---

## Checking Feature Availability in Code

There are three supported approaches, from most to least convenient.

### Approach 1 — `platform_feature()` global helper

The simplest way to gate a code path on a feature. Returns `true` if the feature is available
on the *current* runtime platform.

```php
// Show the native camera button only when native camera is available
if (platform_feature('camera')) {
    // Render NativePHP camera UI
}

// Fall back to WebRTC capture on website platforms
if (platform_feature('webrtc')) {
    // Render <video> element and getUserMedia() controls
}

// Conditionally enable "Save to Device" option
if (platform_feature('file_system')) {
    $actions[] = Action::make('save_to_device')
        ->label('Save to Device')
        ->action(fn () => $this->exportToFile());
}

// Show badge count setter only on mobile
if (platform_feature('app_badge')) {
    $this->updateBadgeCount($unreadCount);
}
```

Internally this function resolves `runtime_platform()` and delegates to
`PlatformFeatureRegistry::isAvailable()`.

---

### Approach 2 — `$platform->hasFeature()` on the enum instance

Call `hasFeature()` directly on a `RuntimePlatform` instance when you already have the enum
value in scope. This is useful in service classes that receive the platform as a dependency.

```php
use App\Enums\RuntimePlatform;

class PlatformNotificationService
{
    public function notify(RuntimePlatform $platform, string $message): void
    {
        if ($platform->hasFeature('push_notifications')) {
            // Send FCM / APNs push notification
            $this->sendPush($message);
        } elseif ($platform->hasFeature('desktop_notifications')) {
            // Send OS desktop notification via NativePHP
            $this->sendDesktopNotification($message);
        } else {
            // Fall back to Filament in-app notification (all platforms)
            $this->sendInAppNotification($message);
        }
    }
}
```

The enum also exposes dedicated boolean methods for the most common checks:

```php
$platform = app('runtime.platform'); // RuntimePlatform

$platform->hasNativeCameraAccess();   // true for MobileApp* and DesktopApp*
$platform->hasWebRTCAccess();         // true for Website* only
$platform->hasFileSystemAccess();     // true for MobileApp* and DesktopApp*
$platform->hasDesktopNotifications(); // true for DesktopApp* only
$platform->hasPushNotifications();    // true for MobileApp* only
$platform->hasAutoUpdates();          // true for DesktopApp* only
$platform->hasAppBadge();             // true for MobileApp* only

// Generic feature check (delegates to PlatformFeatureRegistry)
$platform->hasFeature('camera');

// Get all features as a string array
$platform->getAvailableFeatures(); // e.g. ['camera', 'file_system', 'push_notifications', 'app_badge']
```

---

### Approach 3 — `PlatformFeatureRegistry` directly

Inject the registry when you need to check features against a platform that may differ from
the current request (e.g. admin tooling, reporting, or tests).

```php
use App\Enums\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry;

class PlatformCapabilityReport
{
    public function __construct(private PlatformFeatureRegistry $registry) {}

    /** Returns all features supported by a given platform. */
    public function featuresFor(RuntimePlatform $platform): array
    {
        return $this->registry->getAvailableFeatures($platform);
        // e.g. for DesktopAppMacOS → ['camera', 'file_system', 'desktop_notifications', 'auto_updates']
    }

    /** Returns all platforms that support a given feature. */
    public function platformsFor(string $feature): array
    {
        return $this->registry->getPlatformsForFeature($feature);
        // e.g. for 'push_notifications' → [MobileAppAndroid, MobileAppIos]
    }

    /** Check a specific feature on a specific platform. */
    public function isSupported(string $feature, RuntimePlatform $platform): bool
    {
        return $this->registry->isAvailable($feature, $platform);
    }
}

// Usage
$report = app(PlatformCapabilityReport::class);

$report->isSupported('auto_updates', RuntimePlatform::DesktopAppWindows); // true
$report->isSupported('auto_updates', RuntimePlatform::MobileAppAndroid);  // false
$report->isSupported('webrtc',       RuntimePlatform::WebsiteIos);        // true

// Check all 8 platforms for a feature
foreach (RuntimePlatform::cases() as $platform) {
    $has = $report->isSupported('camera', $platform);
    echo "{$platform->label()}: " . ($has ? 'yes' : 'no') . PHP_EOL;
}
```

---

## CBIR Camera Mode Selection

The CBIR image search feature selects its camera API based on the current platform. Use
`cbirCameraMode()` to branch between native and WebRTC capture:

```php
$platform  = app('runtime.platform');   // RuntimePlatform
$cameraMode = $platform->cbirCameraMode(); // 'native' or 'webrtc'

if ($cameraMode === 'native') {
    // NativePHP Mobile Camera sheet (mobile) or
    // NativePHP Electron Camera dialog (desktop)
    return view('cbir.camera-native');
} else {
    // Browser MediaDevices.getUserMedia() via <video> element
    return view('cbir.camera-webrtc');
}
```

| `RuntimePlatform` case | `cbirCameraMode()` | Backing API |
|---|---|---|
| `WebsiteWindows` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteMacOS` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteAndroid` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteIos` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `MobileAppAndroid` | `'native'` | NativePHP Mobile Camera API |
| `MobileAppIos` | `'native'` | NativePHP Mobile Camera API |
| `DesktopAppWindows` | `'native'` | NativePHP Electron Camera API |
| `DesktopAppMacOS` | `'native'` | NativePHP Electron Camera API |

---

## Feature Availability by Platform Group

A quick summary grouped by the three platform categories:

| Category | Platforms | Available features |
|---|---|---|
| **Website** | `WebsiteWindows`, `WebsiteMacOS`, `WebsiteAndroid`, `WebsiteIos` | `webrtc` |
| **Mobile App** | `MobileAppAndroid`, `MobileAppIos` | `camera`, `file_system`, `push_notifications`, `app_badge` |
| **Desktop App** | `DesktopAppWindows`, `DesktopAppMacOS` | `camera`, `file_system`, `desktop_notifications`, `auto_updates` |

You can check which category is active using the `RuntimePlatform` category methods:

```php
$platform = app('runtime.platform');

$platform->isWebsite();    // Website* cases
$platform->isMobileApp();  // MobileApp* cases
$platform->isDesktopApp(); // DesktopApp* cases
```

These three methods are mutually exclusive — exactly one returns `true` for any platform case.

---

## Related Documentation

- [Platform Support Architecture](platform-support.md) — full architecture overview, detection pipeline, and data flow
- [Environment Configuration](environment-configuration.md) — per-platform `.env` files and variable merging
- [Asset Compilation](asset-compilation.md) — Vite entry points and build directories per platform
- `app/Support/Platform/PlatformFeatureRegistry.php` — feature matrix source of truth
- `app/Enums/RuntimePlatform.php` — enum definition with all feature helper methods
- `app/helpers.php` — `platform_feature()`, `runtime_platform()`, and mode helpers
