# Arsitektur Dukungan Platform (Capacitor)

Dokumen ini menjelaskan bagaimana aplikasi Laravel Wedding Organizer CBIR menangani
tiga lingkungan runtime: browser web, shell mobile Capacitor (Android/iOS), dan
shell desktop Capacitor (Electron untuk Windows/macOS).

> **Catatan penting:** Semua shell Capacitor hanyalah *WebView loader* yang memuat
> server Laravel Anda melalui HTTP. **Tidak ada PHP tertanam, tidak ada bridge
> native, dan tidak ada database proxy.** Server Laravel adalah satu-satunya
> backend; shell hanya menampilkan UI yang sudah dibangun oleh Vite.

---

## Mode Platform

Aplikasi mendukung tiga mode platform, masing-masing dipicu oleh perintah Artisan
khusus. Semua tiga perintah pada akhirnya menjalankan `php artisan serve` yang
sama — perbedaan hanya pada file `.env.{mode}` yang dimuat dan direktori build
Vite (`public/build/{web,mobile,desktop}`) yang dipilih.

### Mode Web

```bash
php artisan serve:web
# atau cukup
php artisan serve
```

Menyajikan aplikasi untuk browser web standar. Menggunakan penanganan
sesi/cookie Laravel standar dan WebRTC untuk akses kamera.

**Kasus RuntimePlatform yang dihasilkan:**
- `WebsiteWindows` — browser desktop Windows/Linux
- `WebsiteMacOS` — Safari/Chrome/Firefox macOS
- `WebsiteAndroid` — Chrome/Firefox di Android
- `WebsiteIos` — Safari di iPhone/iPad

### Mode Mobile (Capacitor Shell)

```bash
php artisan serve:mobile
```

Menjalankan server Laravel yang diload oleh shell Capacitor mobile (Android/iOS).
Shell berada di `app/Capacitor/UserApp` dan `app/Capacitor/AdminApp`. Server
harus dijangkau via jaringan (mis. `http://10.0.2.2:8001` untuk emulator).

**Kasus RuntimePlatform yang dihasilkan:**
- `MobileAppAndroid`
- `MobileAppIos`

### Mode Desktop (Capacitor Electron)

```bash
php artisan serve:desktop
```

Menjalankan server Laravel yang diload oleh shell Capacitor Electron
(Windows/macOS). Sama seperti mobile, shell adalah WebView biasa.

**Kasus RuntimePlatform yang dihasilkan:**
- `DesktopAppWindows`
- `DesktopAppMacOS`

---

## Hubungan Perintah, Mode, dan RuntimePlatform

| Perintah | PlatformMode | RuntimePlatform yang Mungkin |
|---|---|---|
| `php artisan serve:web` | `Web` | `WebsiteWindows`, `WebsiteMacOS`, `WebsiteAndroid`, `WebsiteIos` |
| `php artisan serve:mobile` | `Mobile` | `MobileAppAndroid`, `MobileAppIos` |
| `php artisan serve:desktop` | `Desktop` | `DesktopAppWindows`, `DesktopAppMacOS` |

> **Override:** Proses tanpa argv (queue worker, scheduler) menggunakan
> `PLATFORM_MODE=mobile|desktop|web` env var untuk memilih mode.

---

## Enum RuntimePlatform

`App\Enums\RuntimePlatform` mewakili delapan kasus runtime spesifik.

```php
enum RuntimePlatform: string
{
    // Mode Web → 4 kasus website
    case WebsiteWindows  = 'website_windows';
    case WebsiteMacOS    = 'website_macos';
    case WebsiteAndroid  = 'website_android';
    case WebsiteIos      = 'website_ios';

    // Mode Mobile → 2 kasus aplikasi mobile
    case MobileAppAndroid = 'mobile_app_android';
    case MobileAppIos     = 'mobile_app_ios';

    // Mode Desktop → 2 kasus aplikasi desktop
    case DesktopAppWindows = 'desktop_app_windows';
    case DesktopAppMacOS   = 'desktop_app_macos';
}
```

### Metode helper kategori

```php
$platform->isWebsite();    // true untuk semua kasus Website*
$platform->isMobileApp();  // true untuk kasus MobileApp*
$platform->isDesktopApp(); // true untuk kasus DesktopApp*
```

### Metode helper fitur

```php
$platform->hasNativeCameraAccess();  // MobileApp* + DesktopApp*
$platform->hasWebRTCAccess();        // hanya Website*
$platform->hasFileSystemAccess();    // MobileApp* + DesktopApp*
$platform->hasDesktopNotifications(); // hanya DesktopApp*
$platform->hasPushNotifications();   // hanya MobileApp*
$platform->hasAutoUpdates();         // hanya DesktopApp*
$platform->hasAppBadge();            // hanya MobileApp*

// Delegasikan ke PlatformFeatureRegistry:
$platform->hasFeature('camera');
$platform->getAvailableFeatures();   // mengembalikan string[]
```

### Mode kamera CBIR

```php
$platform->cbirCameraMode(); // 'native' (mobile/desktop) atau 'webrtc' (website)
```

> `'native'` sekarang berarti: shell membuka `<input type="file" capture>`
> tersembunyi. Bukan bridge NativePHP (yang sudah dihapus).

---

## Ikhtisar Komponen

### PlatformCommandDetector

**Lokasi:** `app/Support/Platform/PlatformCommandDetector/PlatformCommandDetector.php`

Membaca `$_SERVER['argv']` selama bootstrap untuk mengidentifikasi perintah
Artisan, lalu memetakannya ke `PlatformMode`.

| Perintah | Mode yang Terdeteksi |
|---|---|
| `artisan serve:web` | `PlatformMode::Web` |
| `php artisan serve` | `PlatformMode::Web` |
| `artisan serve:mobile` | `PlatformMode::Mobile` |
| `artisan serve:desktop` | `PlatformMode::Desktop` |
| Perintah lain / tanpa artisan | Fallback ke `PLATFORM_MODE` env atau `Web` |

```php
$mode = PlatformCommandDetector::detectMode(); // PlatformMode
```

---

### RuntimePlatformDetector

**Lokasi:** `app/Support/Platform/RuntimePlatformDetector/RuntimePlatformDetector.php`

Diberikan `PlatformMode` dan `Request` HTTP opsional, mempersempit ke kasus
`RuntimePlatform` yang tepat.

- **Mode Web** — parse `User-Agent`: `iphone/ipad/ipod` → iOS, `android` → Android, `mac/darwin` → macOS, default → Windows.
- **Mode Mobile** — cek cookie `app_shell=user|admin` + header `X-Capacitor-Platform` (android/ios). Default Android.
- **Mode Desktop** — cek cookie `app_shell` + `X-Capacitor-Platform` (electron/windows/macos). Default Windows.

Exception → log warning + return `RuntimePlatform::WebsiteWindows`.

```php
$detector = app(RuntimePlatformDetector::class);
$runtime  = $detector->detect($mode, $request);
```

---

### EnvironmentManager

**Lokasi:** `app/Support/Platform/EnvironmentManager/EnvironmentManager.php`

Memuat dan menggabungkan file `.env.{mode}` khusus platform di atas `.env`
dasar. Nilai khusus platform mendapat prioritas.

| Mode Platform | File yang dimuat |
|---|---|
| Web | `.env.web` |
| Mobile | `.env.mobile` |
| Desktop | `.env.desktop` |

```php
$envManager = app(EnvironmentManager::class);
$envManager->loadPlatformEnvironment($mode);
$envManager->platformEnvironmentExists($mode); // bool
```

---

### PlatformAssetManager

**Lokasi:** `app/Support/Platform/PlatformAssetManager/PlatformAssetManager.php`

Resolve jalur output build Vite dan entri manifest khusus platform.

| Mode Platform | Direktori build | Titik masuk Vite |
|---|---|---|
| Web | `public/build/web` | `resources/js/app-web/app-web.js` |
| Mobile | `public/build/mobile` | `resources/js/app-mobile/app-mobile.js` |
| Desktop | `public/build/desktop` | `resources/js/app-desktop/app-desktop.js` |

```php
$assetManager = app(PlatformAssetManager::class);
$assetManager->configure($mode);

$assetManager->getBuildDirectory();  // mis. "build/mobile"
$assetManager->getManifestPath();    // path absolut ke manifest.json
$assetManager->getViteInput();       // mis. "resources/js/app-mobile/app-mobile.js"
$assetManager->asset('...');         // URL berversi
$assetManager->manifestExists();     // bool
```

---

### PlatformFeatureRegistry

**Lokasi:** `app/Support/Platform/PlatformFeatureRegistry/PlatformFeatureRegistry.php`

Mempertahankan matriks fitur statis memetakan nama fitur ke kasus
`RuntimePlatform` yang mendukungnya.

```php
$registry = app(PlatformFeatureRegistry::class);

$registry->isAvailable('camera', RuntimePlatform::MobileAppAndroid); // true
$registry->isAvailable('webrtc', RuntimePlatform::DesktopAppWindows); // false

$registry->getAvailableFeatures(RuntimePlatform::DesktopAppMacOS);
// ['camera', 'desktop_notifications', 'file_system', 'auto_updates']

$registry->getPlatformsForFeature('push_notifications');
// [RuntimePlatform::MobileAppAndroid, RuntimePlatform::MobileAppIos]
```

### Matriks Ketersediaan Fitur (Capacitor)

| Fitur | Website* | MobileApp* | DesktopApp* |
|---|---|---|---|
| `camera` | ❌ | ✅ (input file) | ✅ (input file) |
| `webrtc` | ✅ | ❌ | ❌ |
| `file_system` | ❌ | ✅ (input file) | ✅ (input file) |
| `desktop_notifications` | ❌ | ❌ | ✅ (Web Notifications API) |
| `push_notifications` | ❌ | ✅ (FCM) | ❌ |
| `auto_updates` | ❌ | ❌ | ✅ (electron-updater) |
| `app_badge` | ❌ | ✅ | ❌ |

> **Catatan:** `camera` dan `file_system` di Mobile/Desktop menggunakan
> `<input type="file" capture>` yang dibuka oleh shell, bukan bridge native.
> WebRTC hanya di browser karena butuh secure context (`https`) — shell
> produksi sudah `https` jadi WebRTC *bisa* dipakai di shell jika diinginkan,
> tapi arsitektur standar pakai input file.

---

### PlatformModeServiceProvider

**Lokasi:** `app/Providers/PlatformModeServiceProvider.php`

Perekat yang menghubungkan semuanya selama bootstrap. Harus didaftarkan
**sebelum** `RouteServiceProvider` agar singleton `platform.mode` tersedia
saat rute kondisional dimuat.

**Fase `register()`:**
- `PlatformCommandDetector::detectMode()` → bind `app('platform.mode')`
- Daftarkan `RuntimePlatformDetector`, `EnvironmentManager`, `PlatformAssetManager` sebagai singleton

**Fase `boot()`:**
1. Resolve `platform.mode`
2. `EnvironmentManager::loadPlatformEnvironment($mode)` — merge `.env.{mode}`
3. Bind `app('runtime.platform')` via `RuntimePlatformDetector::detect()`
4. `PlatformAssetManager::configure($mode)` — set direktori build
5. Di `local`, log semua detail deteksi

---

## Alur Bootstrap

```
1. Developer menjalankan: php artisan serve:mobile
        │
        ▼
2. PlatformCommandDetector membaca argv[1] = "serve:mobile"
   → mengembalikan PlatformMode::Mobile
        │
        ▼
3. PlatformModeServiceProvider::register()
   → bind app('platform.mode') = PlatformMode::Mobile
        │
        ▼
4. PlatformModeServiceProvider::boot()
   → EnvironmentManager memuat .env.mobile
     (SESSION_DRIVER=database, VITE_PLATFORM=mobile, dll.)
        │
        ▼
5. RuntimePlatformDetector::detect(PlatformMode::Mobile, $request)
   → cek cookie app_shell + header X-Capacitor-Platform
   → mengembalikan RuntimePlatform::MobileAppAndroid (atau MobileAppIos)
   → bind app('runtime.platform')
        │
        ▼
6. PlatformAssetManager::configure(PlatformMode::Mobile)
   → direktori build = "build/mobile"
   → manifest = public/build/mobile/manifest.json
        │
        ▼
7. Aplikasi sepenuhnya dikonfigurasi
   - Views meminta aset via PlatformAssetManager::asset(...)
   - Filament panel /user atau /admin termuat
```

---

## Fungsi Helper Global

Di `app/helpers.php`:

```php
// Dapatkan mode platform saat ini
$mode = platform_mode();              // PlatformMode::Mobile
$mode->label();                       // "Mobile (Capacitor)"
$mode->environmentFile();             // ".env.mobile"
$mode->assetDirectory();              // "build/mobile"
$mode->allowsCameraAccess();          // true (via input file)

// Dapatkan runtime platform spesifik
$runtime = runtime_platform();        // RuntimePlatform::MobileAppAndroid
$runtime->label();                    // "Mobile App (Android)"
$runtime->isMobileApp();              // true

// Cek fitur
if (platform_feature('camera')) { ... }

// Logika kondisional
if (is_mobile_mode()) { ... }
elseif (is_desktop_mode()) { ... }
else { ... }
```

---

## Pemilihan Mode Kamera CBIR

```php
$mode = runtime_platform()->cbirCameraMode(); // 'native' | 'webrtc'

match ($mode) {
    'native'  => $this->openShellFileInput(),   // dispatch capacitor-camera-open-input
    'webrtc'  => $this->captureWithWebRTC(),
};
```

---

## Pemeriksaan Fitur dalam Blade

```blade
@if(platform_feature('camera'))
    {{-- Shell membuka input file tersembunyi --}}
    <x-cbir-camera-options />
@elseif(platform_feature('webrtc'))
    {{-- Browser WebRTC viewfinder --}}
    <x-webrtc-camera-button />
@endif

@if(platform_feature('push_notifications'))
    <x-notification-opt-in-prompt />
@endif
```

---

## Konfigurasi Build Vite

```bash
# Web
npm run build:web       # VITE_PLATFORM=web

# Mobile
npm run build:mobile    # VITE_PLATFORM=mobile

# Desktop
npm run build:desktop   # VITE_PLATFORM=desktop

# Semua sekaligus
npm run build:all
```

Lokasi output:

```
public/
  build/
    web/
      manifest.json
      assets/app-web.{hash}.js
    mobile/
      manifest.json
      assets/app-mobile.{hash}.js
    desktop/
      manifest.json
      assets/app-desktop.{hash}.js
```

CI/CD (`.github/workflows/build-mobile.yml`, `build-desktop.yml`) mengasert
keberadaan `public/build/mobile/.vite/manifest.json` dan
`public/build/desktop/.vite/manifest.json`.

---

## Shell Capacitor: Konfigurasi URL

Setiap shell memiliki `capacitor.config.json` dan `src/js/launcher.js`. Jangan
edit manual — gunakan skrip:

```bash
cd app/Capacitor/UserApp
npm run set:url -- https://dekorasi.example.com/user
# atau untuk admin
cd app/Capacitor/AdminApp
npm run set:url -- https://dekorasi.example.com/admin
```

Skrip menulis `server.url` (Android/iOS) dan `PANEL_URL` (Electron) sekaligus,
dan menyelaraskan `androidScheme`/`iosScheme`/`cleartext`:
- `http://` → cleartext=true, scheme=http (dev only)
- `https://` → cleartext=false, scheme=https (produksi)

---

## Kamera di Capacitor

| Bundle | Perilaku |
|---|---|
| `build/web` | WebRTC `getUserMedia()` viewfinder langsung |
| `build/mobile` | Shell dispatch `capacitor-camera-open-input` → `<input type="file" capture="environment">` |
| `build/desktop` | Shell dispatch `capacitor-camera-open-input` → `<input type="file" capture="user">` |

Sisi server: `CbirSearchPage` dispatch event Alpine
`capacitor-camera-open-input`. Partial
`resources/views/{User,Welcome}/components/cbir-camera-options` memiliki
listener `x-on:capacitor-camera-open-input.window` yang memicu
`openShellSource(mode)` pada input tersembunyi `photo` (belakang) atau
`photoFront` (depan).

---

## Notifikasi

| Saluran | Website | MobileApp | DesktopApp |
|---|---|---|---|
| Database (Filament) | ✅ | ✅ | ✅ |
| WebSocket Broadcast | ✅ | ✅ | ✅ |
| FCM Push | ❌ | ✅ | ❌ |
| OS Toast/Desktop Notif | ❌ (pakai Web Notif API) | ❌ | ✅ (Web Notif API via Electron) |

Semua platform menerima notifikasi via DB record + broadcast. FCM hanya untuk
mobile saat app background. Desktop pakai Web Notifications API (standar
browser, tersedia di Electron).

---

## Dokumentasi Terkait

- [Panduan Mobile](../deployment/mobile/mobile.md) — build Android/iOS
- [Panduan Desktop](../deployment/desktop/desktop.md) — build Windows/macOS
- [Kompilasi Aset](../asset-compilation.md) — pipeline Vite
- [Konfigurasi Lingkungan](../environment-configuration.md) — `.env.*`
- [Fitur Platform](../platform-features.md) — detail fitur per platform
- [Pohon Keputusan Perintah](../command-decision-tree.md) — flowchart pemilihan perintah
- [Panduan Perintah](../command-guide.md) — referensi perintah lengkap