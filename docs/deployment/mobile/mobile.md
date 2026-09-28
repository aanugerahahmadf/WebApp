# Panduan Deployment Aplikasi Mobile ke Toko Aplikasi

Panduan ini mencakup proses membangun dan menerbitkan aplikasi Laravel Wedding Organizer CBIR untuk Android (Google Play Store) dan iOS (Apple App Store) menggunakan [NativePHP Mobile](https://github.com/nativephp/mobile).

---

## Prasyarat

| Persyaratan | Detail |
|---|---|
| Paket `nativephp/mobile` | `composer require nativephp/mobile` |
| PHP 8.2+ CLI | Diperlukan di mesin build |
| Node.js 18+ | Diperlukan untuk kompilasi aset |
| Android SDK + ADB | Diperlukan untuk build Android |
| Xcode 15+ | Diperlukan untuk build iOS (hanya macOS) |
| Akun Apple Developer | Diperlukan untuk pengiriman ke App Store iOS |
| Akun Google Play Developer | Diperlukan untuk pengiriman ke Play Store Android |

Instal dan siapkan NativePHP Mobile sekali per proyek:

```bash
composer require nativephp/mobile
php artisan native:install
```

---

## Konfigurasi Lingkungan

Buat `.env.mobile` dari file contoh:

```bash
cp .env.mobile.example .env.mobile
```

Variabel yang diperlukan di `.env.mobile`:

```dotenv
APP_ENV=production
APP_KEY=base64:...          # Buat dengan: php artisan key:generate
APP_URL=https://your-api.com

# Session — driver database bertahan saat aplikasi di-restart
SESSION_DRIVER=database
SESSION_LIFETIME=10080

# Penanda platform mobile
VITE_PLATFORM=mobile

# Identitas aplikasi NativePHP Mobile
NATIVEPHP_APP_ID=com.yourcompany.weddingorganizer
NATIVEPHP_APP_NAME="Wedding Flower Decorations"
NATIVEPHP_APP_VERSION=1.0.0
NATIVEPHP_APP_VERSION_CODE=1
```

### Referensi Variabel yang Diperlukan

| Variabel | Wajib | Deskripsi |
|---|---|---|
| `APP_ENV` | Ya | Harus `production` untuk build toko |
| `APP_KEY` | Ya | Kunci enkripsi base64 32-byte |
| `APP_URL` | Ya | URL server API backend |
| `SESSION_DRIVER` | Ya | Gunakan `database` untuk mobile |
| `VITE_PLATFORM` | Ya | Harus `mobile` |
| `NATIVEPHP_APP_ID` | Ya | Identifier bundle reverse-DNS yang unik |
| `NATIVEPHP_APP_VERSION` | Ya | String versi semantik (mis. `1.0.0`) |
| `NATIVEPHP_APP_VERSION_CODE` | Ya | Kode versi integer, tingkatkan pada setiap rilis |

---

## Langkah Build

### 1. Instal dependensi

```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 2. Kompilasi aset mobile

```bash
npm run build:mobile
```

Aset di-output ke `public/build/mobile/`.

### 3. Cache konfigurasi Laravel

```bash
php artisan optimize
```

### 4. Jalankan migrasi database (jika menggunakan DB remote)

```bash
php artisan migrate --force
```

---

## Build Android

### Build Pengembangan / Debug

Deploy langsung ke perangkat atau emulator yang terhubung:

```bash
# Deteksi otomatis perangkat Android yang terhubung
php artisan native:run android

# Tentukan perangkat berdasarkan serial ADB
php artisan native:run android <device-serial>

# Mode watch (hot reload selama pengembangan)
php artisan native:run android --watch
```

### Release Build (APK)

```bash
php artisan native:run android --build=release
```

Output: `build/android/app-release.apk`

### App Bundle (Play Store)

Play Store memerlukan Android App Bundle (`.aab`) alih-alih APK biasa:

```bash
php artisan native:run android --build=bundle
```

Output: `build/android/app-release.aab`

### Keystore Android (Penandatanganan Kode)

Buat keystore untuk menandatangani release build. **Simpan file keystore dan kata sandi dengan aman — kehilangan keduanya berarti Anda tidak dapat memperbarui aplikasi.**

```bash
keytool -genkey -v \
  -keystore wedding-organizer-release.jks \
  -alias wedding-organizer \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

Konfigurasi penandatanganan di `nativephp.php` atau lewatkan melalui variabel lingkungan:

```dotenv
NATIVEPHP_ANDROID_KEYSTORE_PATH=/path/to/wedding-organizer-release.jks
NATIVEPHP_ANDROID_KEYSTORE_PASSWORD=your-keystore-password
NATIVEPHP_ANDROID_KEY_ALIAS=wedding-organizer
NATIVEPHP_ANDROID_KEY_PASSWORD=your-key-password
```

### Konfigurasi Build Android

Pengaturan utama di `config/nativephp.php` (atau konfigurasi NativePHP yang setara):

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

## Build iOS

> **Build iOS memerlukan macOS.** Anda tidak dapat build untuk iOS di Windows atau Linux.

### Build Pengembangan

```bash
# Deploy ke perangkat atau simulator iOS yang terhubung
php artisan native:run ios

# Mode watch
php artisan native:run ios --watch
```

### Release Build

```bash
php artisan native:run ios --build=release
```

Ini menghasilkan arsip `.ipa` yang siap untuk pengiriman ke TestFlight atau App Store.

### Penandatanganan Kode iOS

Aplikasi iOS harus ditandatangani dengan sertifikat Apple Developer dan provisioning profile. Siapkan melalui Xcode atau baris perintah:

1. Buka Xcode → Preferences → Accounts → tambahkan Apple ID Anda.
2. Di target proyek, atur Team dan Bundle Identifier agar cocok dengan `NATIVEPHP_APP_ID`.
3. Aktifkan penandatanganan otomatis, atau pilih sertifikat distribusi dan provisioning profile secara manual.

Variabel lingkungan yang diperlukan untuk penandatanganan CI otomatis:

```dotenv
NATIVEPHP_IOS_TEAM_ID=ABCDE12345
NATIVEPHP_IOS_BUNDLE_ID=com.yourcompany.weddingorganizer
NATIVEPHP_IOS_CERTIFICATE_PATH=/path/to/distribution.p12
NATIVEPHP_IOS_CERTIFICATE_PASSWORD=cert-password
NATIVEPHP_IOS_PROVISIONING_PROFILE=/path/to/profile.mobileprovision
```

### Konfigurasi Build iOS

Pengaturan utama di `config/nativephp.php`:

```php
'ios' => [
    'deployment_target'   => '16.0',   // iOS 16+
    'device_families'     => ['iphone', 'ipad'],
    'permissions'         => [
        'NSCameraUsageDescription'       => 'Diperlukan untuk pencarian gambar CBIR.',
        'NSPhotoLibraryUsageDescription' => 'Diperlukan untuk memilih gambar pencarian.',
    ],
],
```

---

## Pengiriman ke Toko Aplikasi

### Google Play Store

1. Buat aplikasi di [Google Play Console](https://play.google.com/console).
2. Siapkan kunci penandatanganan aplikasi — gunakan Play App Signing untuk opsi pemulihan kunci terbaik.
3. Unggah bundel `.aab` ke track yang diinginkan (Internal → Alpha → Beta → Produksi).
4. Lengkapi Daftar Toko: screenshot, deskripsi, rating konten, URL kebijakan privasi.
5. Kirim untuk ditinjau.

**Daftar periksa rilis:**

```
☐ APP_ENV=production di .env.mobile
☐ NATIVEPHP_APP_VERSION_CODE ditingkatkan
☐ AAB ditandatangani dengan keystore rilis
☐ Target SDK = 34 (Android 14 — persyaratan Play Store saat ini)
☐ URL kebijakan privasi diatur
☐ Minimal 2 screenshot per tipe perangkat
☐ Kuesioner rating konten dilengkapi
```

### Apple App Store

1. Buat catatan aplikasi di [App Store Connect](https://appstoreconnect.apple.com).
2. Arsipkan aplikasi di Xcode (Product → Archive) atau gunakan `native:run ios --build=release`.
3. Unggah `.ipa` ke App Store Connect melalui Xcode Organizer atau `xcrun altool`.
4. Lengkapi daftar App Store: screenshot, deskripsi, kata kunci, URL dukungan.
5. Kirim untuk App Review.

**Daftar periksa rilis:**

```
☐ APP_ENV=production di .env.mobile
☐ Versi bundle ditingkatkan (CFBundleVersion)
☐ Ditandatangani dengan sertifikat Distribution + provisioning profile App Store
☐ Semua deskripsi penggunaan izin yang diperlukan diisi di Info.plist
☐ URL kebijakan privasi diatur
☐ Minimal 1 screenshot per perangkat yang diperlukan (iPhone 6.5", iPhone 5.5", iPad Pro 12.9")
☐ Informasi App Review: kredensial akun demo diberikan
```

---

## Verifikasi Pasca-Deployment

Setelah merilis rilis baru, verifikasi deployment:

```bash
# Konfirmasi mode platform mobile terdeteksi dengan benar
php artisan platform:status

# Verifikasi manifest aset mobile ada
ls -la public/build/mobile/manifest.json

# Uji asap backend API dari perangkat
curl https://your-api.com/api/health
```

---

## Rollback Rilis

- **Google Play:** Hentikan rollout dari Play Console → Release → Production → Manage rollout → Halt.
- **Apple App Store:** Anda tidak dapat menghapus versi yang sudah disetujui. Siapkan rilis hotfix dan kirimkan untuk expedited review.

---

## Dokumentasi Terkait

- [Kompilasi Aset](../asset-compilation.md) — cara kerja `npm run build:mobile`
- [Konfigurasi Lingkungan](../environment-configuration.md) — referensi variabel `.env.mobile`
- `.env.mobile.example` — file starter beranotasi
- [Contoh Pipeline CI/CD](./../deployment-ci.md) — alur kerja build otomatis
