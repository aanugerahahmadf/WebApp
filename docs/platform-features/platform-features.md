# Matriks Fitur Platform (Capacitor)

Dokumen ini menjelaskan fitur-fitur aplikasi yang tersedia di masing-masing dari delapan
runtime platform yang didukung, menjelaskan apa yang diaktifkan setiap fitur dalam
aplikasi Wedding Organizer CBIR, dan menampilkan tiga cara untuk memeriksa
ketersediaan fitur dari kode PHP.

> **Catatan:** Semua shell Capacitor hanyalah *WebView loader* — tidak ada PHP
> tertanam, tidak ada bridge native. Fitur "native" di sisi server berarti
> server mendispatch event Alpine `capacitor-camera-open-input` dan shell
> membuka `<input type="file" capture>`. WebRTC tetap hanya di browser karena
> butuh secure context (`https`).

---

## Matriks Fitur

Aplikasi mendefinisikan tujuh fitur bernama. Setiap fitur dilacak oleh
`PlatformFeatureRegistry` dan dipetakan ke kasus `RuntimePlatform` yang
mendukungnya.

| Fitur | Website<br>Windows | Website<br>macOS | Website<br>Android | Website<br>iOS | Mobile App<br>Android | Mobile App<br>iOS | Desktop App<br>Windows | Desktop App<br>macOS |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `camera` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| `webrtc` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `file_system` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| `desktop_notifications` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| `push_notifications` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `auto_updates` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| `app_badge` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |

> **Keterangan:** ✅ Tersedia &nbsp;|&nbsp; ❌ Tidak tersedia

### Referensi kolom platform

| Label kolom | Kasus `RuntimePlatform` | Diluncurkan oleh |
|---|---|---|
| Website Windows | `WebsiteWindows` | `php artisan serve:web` pada browser Windows/Linux |
| Website macOS | `WebsiteMacOS` | `php artisan serve:web` pada browser macOS |
| Website Android | `WebsiteAndroid` | `php artisan serve:web` pada browser Android |
| Website iOS | `WebsiteIos` | `php artisan serve:web` pada browser iPhone/iPad |
| Mobile App Android | `MobileAppAndroid` | `php artisan serve:mobile` + shell Capacitor Android |
| Mobile App iOS | `MobileAppIos` | `php artisan serve:mobile` + shell Capacitor iOS |
| Desktop App Windows | `DesktopAppWindows` | `php artisan serve:desktop` + shell Electron Windows |
| Desktop App macOS | `DesktopAppMacOS` | `php artisan serve:desktop` + shell Electron macOS |

---

## Apa yang Diaktifkan Setiap Fitur

### `camera`

**Tersedia di:** Aplikasi Mobile (Capacitor), Aplikasi Desktop (Capacitor Electron)

Memberikan akses kamera untuk fitur Content-Based Image Retrieval (CBIR).
Implementasi berbeda per platform:

- **Mobile (Capacitor):** Server mendispatch event Alpine
  `capacitor-camera-open-input` → shell membuka `<input type="file" capture="environment">`
  (kamera belakang) atau `capture="user"` (kamera depan/depan).
- **Desktop (Electron):** Sama seperti mobile, shell membuka input file tersembunyi.
  WebRTC *bisa* dipakai di Electron produksi (karena `https`), tapi arsitektur
  standar pakai input file.
- **Website:** Menggunakan WebRTC (`webrtc` feature), bukan `camera`.

Metode enum `$platform->cbirCameraMode()` mengembalikan `'native'` untuk
MobileApp* dan DesktopApp*.

---

### `webrtc`

**Tersedia di:** Semua platform website (browser Windows, macOS, Android, iOS)

Mengaktifkan input kamera melalui API `MediaDevices.getUserMedia()` browser
(WebRTC). Padanan berbasis browser dari fitur `camera` native.

- Metode enum `$platform->cbirCameraMode()` mengembalikan `'webrtc'` untuk
  platform website.
- `camera` dan `webrtc` saling eksklusif — sebuah platform memiliki salah
  satunya, tidak pernah keduanya.

---

### `file_system`

**Tersedia di:** Aplikasi Mobile (Capacitor), Aplikasi Desktop (Capacitor Electron)

Menyediakan akses baca/tulis ke sistem file perangkat melalui input file
tersembunyi yang dibuka shell:

- Menyimpan gambar referensi CBIR yang diambil secara lokal
- Mengekspor ringkasan pesanan dan proposal dekorasi ke file (download biasa)
- Menyimpan cache aset untuk penayangan offline

> Platform website tidak memiliki akses sistem file persisten; `File System Access API`
> browser tidak digunakan oleh aplikasi ini.

---

### `desktop_notifications`

**Tersedia di:** Hanya aplikasi Desktop (Capacitor Electron Windows, macOS)

Mengirim notifikasi OS melalui **Web Notifications API** (standar browser,
tersedia di Electron `BrowserWindow`). Digunakan oleh `PlatformNotificationService`
untuk mengingatkan penyelenggara tentang:

- Pesanan pelanggan baru
- Perubahan status pembayaran (melalui webhook Midtrans)
- Acara terjadwal yang akan datang

> Aplikasi mobile menggunakan `push_notifications` (FCM). Platform website hanya
> mengandalkan notifikasi in-app Filament, meski Web Notifications API juga
> tersedia di browser desktop jika user memberi izin.

---

### `push_notifications`

**Tersedia di:** Hanya aplikasi Mobile (Capacitor Android, iOS)

Mengirimkan push notification ke perangkat pengguna bahkan saat aplikasi
berada di latar belakang via **FCM** (Firebase Cloud Messaging). Digunakan oleh
`PlatformNotificationService` untuk kasus penggunaan yang sama seperti
`desktop_notifications`, tetapi melalui infrastruktur push platform mobile.

---

### `auto_updates`

**Tersedia di:** Hanya aplikasi Desktop (Capacitor Electron Windows, macOS)

Memungkungi shell Electron mengunduh dan menginstal pembaruan aplikasi
secara otomatis di latar belakang via **electron-updater**. Ditangani sepenuhnya
di sisi shell (`electron/main.ts` + `electron-builder.config.js`), bukan
server Laravel.

> Tidak berlaku untuk aplikasi mobile (dikelola oleh toko aplikasi) atau
> website (selalu disajikan fresh).

---

### `app_badge`

**Tersedia di:** Hanya aplikasi Mobile (Capacitor Android, iOS)

Menetapkan jumlah badge numerik pada ikon aplikasi di layar beranda perangkat.
Digunakan untuk menampilkan jumlah pesan yang belum dibaca atau notifikasi
pesanan yang tertunda secara sekilas.

> Di Capacitor, ini memerlukan plugin `@capacitor/push-notifications` atau
> `@capawesome/capacitor-badge` di shell, serta integrasi FCM/APNs.

---

## Memeriksa Ketersediaan Fitur dalam Kode

Ada tiga pendekatan yang didukung, dari yang paling hingga paling tidak mudah
digunakan.

### Pendekatan 1 — helper global `platform_feature()`

Cara paling sederhana untuk membatasi jalur kode pada suatu fitur. Mengembalikan
`true` jika fitur tersedia di runtime platform *saat ini*.

```php
// Tampilkan opsi kamera shell hanya saat fitur camera tersedia
if (platform_feature('camera')) {
    // Render UI yang memdispatch capacitor-camera-open-input
}

// Fallback ke pengambilan WebRTC di platform website
if (platform_feature('webrtc')) {
    // Render <video> element dan kontrol getUserMedia()
}

// Aktifkan opsi "Simpan ke Perangkat" secara kondisional
if (platform_feature('file_system')) {
    $actions[] = Action::make('save_to_device')
        ->label('Save to Device')
        ->action(fn () => $this->exportToFile());
}

// Tampilkan pengatur jumlah badge hanya di mobile
if (platform_feature('app_badge')) {
    $this->updateBadgeCount($unreadCount);
}
```

Secara internal fungsi ini me-resolve `runtime_platform()` dan mendelegasikan ke
`PlatformFeatureRegistry::isAvailable()`.

---

### Pendekatan 2 — `$platform->hasFeature()` pada instance enum

Panggil `hasFeature()` langsung pada instance `RuntimePlatform` ketika Anda
sudah memiliki nilai enum dalam cakupan. Berguna di kelas service.

```php
use App\Enums\RuntimePlatform;

class PlatformNotificationService
{
    public function notify(RuntimePlatform $platform, string $message): void
    {
        if ($platform->hasFeature('push_notifications')) {
            // Kirim push notification FCM
            $this->sendPush($message);
        } elseif ($platform->hasFeature('desktop_notifications')) {
            // Kirim notifikasi OS via Web Notifications API (Electron)
            $this->sendDesktopNotification($message);
        } else {
            // Fallback ke notifikasi in-app Filament (semua platform)
            $this->sendInAppNotification($message);
        }
    }
}
```

Enum juga mengekspos metode boolean khusus untuk pemeriksaan paling umum:

```php
$platform = app('runtime.platform'); // RuntimePlatform

$platform->hasNativeCameraAccess();   // true untuk MobileApp* dan DesktopApp*
$platform->hasWebRTCAccess();         // true hanya untuk Website*
$platform->hasFileSystemAccess();     // true untuk MobileApp* dan DesktopApp*
$platform->hasDesktopNotifications(); // true hanya untuk DesktopApp*
$platform->hasPushNotifications();    // true hanya untuk MobileApp*
$platform->hasAutoUpdates();          // true hanya untuk DesktopApp*
$platform->hasAppBadge();             // true hanya untuk MobileApp*

// Pemeriksaan fitur generik (mendelegasikan ke PlatformFeatureRegistry)
$platform->hasFeature('camera');

// Dapatkan semua fitur sebagai array string
$platform->getAvailableFeatures();
// mis. untuk DesktopAppMacOS → ['camera', 'file_system', 'desktop_notifications', 'auto_updates']
```

---

### Pendekatan 3 — `PlatformFeatureRegistry` secara langsung

Injeksi registry saat Anda perlu memeriksa fitur terhadap platform yang mungkin
berbeda dari request saat ini (mis. tooling admin, pelaporan, atau pengujian).

```php
use App\Enums\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry;

class PlatformCapabilityReport
{
    public function __construct(private PlatformFeatureRegistry $registry) {}

    /** Mengembalikan semua fitur yang didukung oleh platform tertentu. */
    public function featuresFor(RuntimePlatform $platform): array
    {
        return $this->registry->getAvailableFeatures($platform);
        // mis. untuk DesktopAppMacOS → ['camera', 'file_system', 'desktop_notifications', 'auto_updates']
    }

    /** Mengembalikan semua platform yang mendukung fitur tertentu. */
    public function platformsFor(string $feature): array
    {
        return $this->registry->getPlatformsForFeature($feature);
        // mis. untuk 'push_notifications' → [MobileAppAndroid, MobileAppIos]
    }

    /** Memeriksa fitur tertentu pada platform tertentu. */
    public function isSupported(string $feature, RuntimePlatform $platform): bool
    {
        return $this->registry->isAvailable($feature, $platform);
    }
}

// Penggunaan
$report = app(PlatformCapabilityReport::class);

$report->isSupported('auto_updates', RuntimePlatform::DesktopAppWindows); // true
$report->isSupported('auto_updates', RuntimePlatform::MobileAppAndroid);  // false
$report->isSupported('webrtc',       RuntimePlatform::WebsiteIos);        // true

// Periksa semua 8 platform untuk satu fitur
foreach (RuntimePlatform::cases() as $platform) {
    $has = $report->isSupported('camera', $platform);
    echo "{$platform->label()}: " . ($has ? 'yes' : 'no') . PHP_EOL;
}
```

---

## Pemilihan Mode Kamera CBIR

Fitur pencarian gambar CBIR memilih API kameranya berdasarkan platform saat
ini. Gunakan `cbirCameraMode()` untuk memilih antara pengambilan shell dan
WebRTC:

```php
$platform  = app('runtime.platform');   // RuntimePlatform
$cameraMode = $platform->cbirCameraMode(); // 'native' atau 'webrtc'

if ($cameraMode === 'native') {
    // Shell Capacitor membuka <input type="file" capture>
    // Server dispatch event Alpine: capacitor-camera-open-input
    return view('cbir.camera-native');
} else {
    // Browser MediaDevices.getUserMedia() melalui elemen <video>
    return view('cbir.camera-webrtc');
}
```

| Kasus `RuntimePlatform` | `cbirCameraMode()` | API yang digunakan |
|---|---|---|
| `WebsiteWindows` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteMacOS` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteAndroid` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `WebsiteIos` | `'webrtc'` | `MediaDevices.getUserMedia()` |
| `MobileAppAndroid` | `'native'` | Shell: `<input capture="environment">` |
| `MobileAppIos` | `'native'` | Shell: `<input capture="environment">` |
| `DesktopAppWindows` | `'native'` | Shell: `<input capture="user">` |
| `DesktopAppMacOS` | `'native'` | Shell: `<input capture="user">` |

> `'native'` di sini berarti **shell membuka input file tersembunyi**, bukan
> NativePHP bridge (yang sudah dihapus). Nama metode dipertahankan agar 54
> callsite blade/JS tidak perlu diubah; dokumentasi di sini menjelaskan
> semantik baru.

---

## Ketersediaan Fitur berdasarkan Kelompok Platform

Ringkasan cepat dikelompokkan berdasarkan tiga kategori platform:

| Kategori | Platform | Fitur yang tersedia |
|---|---|---|
| **Website** | `WebsiteWindows`, `WebsiteMacOS`, `WebsiteAndroid`, `WebsiteIos` | `webrtc` |
| **Aplikasi Mobile (Capacitor)** | `MobileAppAndroid`, `MobileAppIos` | `camera`, `file_system`, `push_notifications`, `app_badge` |
| **Aplikasi Desktop (Capacitor Electron)** | `DesktopAppWindows`, `DesktopAppMacOS` | `camera`, `file_system`, `desktop_notifications`, `auto_updates` |

Anda dapat memeriksa kategori mana yang aktif menggunakan metode kategori
`RuntimePlatform`:

```php
$platform = app('runtime.platform');

$platform->isWebsite();    // Kasus Website*
$platform->isMobileApp();  // Kasus MobileApp*
$platform->isDesktopApp(); // Kasus DesktopApp*
```

Ketiga metode ini saling eksklusif — tepat satu yang mengembalikan `true`
untuk kasus platform mana pun.

---

## Dokumentasi Terkait

- [Arsitektur Dukungan Platform](platform-support.md) — ikhtisar arsitektur lengkap, pipeline deteksi, dan alur data
- [Konfigurasi Lingkungan](environment-configuration.md) — file `.env` per platform dan penggabungan variabel
- [Kompilasi Aset](asset-compilation.md) — titik masuk Vite dan direktori build per platform
- `app/Support/Platform/PlatformFeatureRegistry/PlatformFeatureRegistry.php` — sumber kebenaran matriks fitur
- `app/Enums/RuntimePlatform/RuntimePlatform.php` — definisi enum dengan semua metode helper fitur
- `app/helpers.php` — `platform_feature()`, `runtime_platform()`, dan helper mode