# Panduan Distribusi Aplikasi Desktop

Panduan ini mencakup proses membangun dan mendistribusikan aplikasi Laravel Wedding Organizer CBIR sebagai aplikasi desktop native untuk Windows dan macOS menggunakan [NativePHP Electron](https://nativephp.com).

---

## Prasyarat

| Persyaratan | Detail |
|---|---|
| Paket `nativephp/electron` | `composer require nativephp/electron` |
| Paket `nativephp/laravel` | `composer require nativephp/laravel` |
| PHP 8.2+ CLI | Diperlukan di mesin build |
| Node.js 18+ | Diperlukan (toolchain Electron) |
| Electron Builder | Diinstal secara otomatis oleh `nativephp/electron` |
| **Build Windows:** Windows 10+  | Cross-compilation dari macOS/Linux terbatas |
| **Build macOS:** macOS 12+ + Xcode | Diperlukan untuk packaging `.app` dan penandatanganan kode |
| Akun Apple Developer | Diperlukan untuk notarization macOS dan distribusi |
| Sertifikat Penandatanganan Kode Windows | Diperlukan untuk penandatanganan Authenticode Windows (opsional namun direkomendasikan) |

Instal dan siapkan NativePHP Desktop sekali per proyek:

```bash
composer require nativephp/electron nativephp/laravel
php artisan native:install
```

---

## Konfigurasi Lingkungan

Buat `.env.desktop` dari file contoh:

```bash
cp .env.desktop.example .env.desktop
```

Variabel yang diperlukan di `.env.desktop`:

```dotenv
APP_ENV=production
APP_KEY=base64:...           # Buat dengan: php artisan key:generate
APP_URL=http://localhost:8002

# Session file lokal pengguna tunggal ideal untuk desktop
SESSION_DRIVER=file
SESSION_LIFETIME=10080

# Penanda platform desktop
VITE_PLATFORM=desktop

# Port server PHP tertanam
NATIVEPHP_HTTP_PORT=8002

# Identitas aplikasi NativePHP
NATIVEPHP_APP_ID=com.yourcompany.weddingorganizer
NATIVEPHP_APP_NAME="Wedding Flower Decorations"
NATIVEPHP_APP_VERSION=1.0.0
```

### Referensi Variabel yang Diperlukan

| Variabel | Wajib | Deskripsi |
|---|---|---|
| `APP_ENV` | Ya | Harus `production` untuk build yang didistribusikan |
| `APP_KEY` | Ya | Kunci enkripsi base64 32-byte |
| `APP_URL` | Ya | Harus cocok dengan `http://localhost:{NATIVEPHP_HTTP_PORT}` |
| `SESSION_DRIVER` | Ya | Gunakan `file` untuk desktop (lokal pengguna tunggal) |
| `VITE_PLATFORM` | Ya | Harus `desktop` |
| `NATIVEPHP_HTTP_PORT` | Ya | Port yang didengarkan server PHP tertanam (default 8002) |
| `NATIVEPHP_APP_ID` | Ya | Identifier bundle reverse-DNS yang unik |
| `NATIVEPHP_APP_VERSION` | Ya | String versi semantik (mis. `1.0.0`) |

---

## Langkah Build

### 1. Instal dependensi

```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 2. Kompilasi aset desktop

```bash
npm run build:desktop
```

Aset di-output ke `public/build/desktop/`.

### 3. Cache konfigurasi Laravel

```bash
php artisan optimize
```

### 4. Build aplikasi Electron

```bash
php artisan native:build
```

Perintah build NativePHP Electron mengemas runtime PHP, aplikasi Laravel, dan shell Electron ke dalam bundel yang dapat didistribusikan.

---

## Packaging Windows

### Pengembangan

Mulai aplikasi dalam mode pengembangan dengan live reloading:

```bash
php artisan native:serve
```

### Installer `.exe` Produksi

```bash
php artisan native:build --os=win
```

Output: `dist/Wedding-Organizer-Setup-{version}.exe` (installer NSIS) dan `dist/Wedding-Organizer-{version}-win.zip` (portable).

### Penandatanganan Kode Windows (Authenticode)

Installer Windows yang tidak ditandatangani akan memicu peringatan SmartScreen. Tandatangani dengan sertifikat Authenticode dari CA tepercaya (DigiCert, Sectigo, dll.):

```dotenv
NATIVEPHP_WINDOWS_CERT_FILE=/path/to/certificate.pfx
NATIVEPHP_WINDOWS_CERT_PASSWORD=your-cert-password
```

Atau gunakan layanan penandatanganan HSM berbasis cloud (Trusted Signing, SignPath):

```yaml
# Dalam konfigurasi electron-builder (nativephp.config.js atau package.json)
win:
  certificateSubjectName: "Your Company Name"
  signingHashAlgorithms: ["sha256"]
  sign: "./scripts/sign-windows.js"
```

### Konfigurasi Build Windows

Pengaturan utama di `config/nativephp.php`:

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

## Packaging macOS

### Pengembangan

```bash
php artisan native:serve
```

### Produksi `.dmg` / `.app`

```bash
php artisan native:build --os=mac
```

Output: `dist/Wedding-Organizer-{version}.dmg` dan `dist/Wedding-Organizer-{version}-mac.zip`.

### Penandatanganan Kode dan Notarisasi macOS

macOS memerlukan penandatanganan kode dengan sertifikat Apple Developer ID. Tanpanya, Gatekeeper akan memblokir aplikasi saat pertama kali diluncurkan. **Notarisasi** diperlukan untuk distribusi di luar Mac App Store pada macOS 10.15+.

Siapkan kredensial:

```dotenv
NATIVEPHP_MACOS_IDENTITY="Developer ID Application: Your Name (TEAMID)"
NATIVEPHP_APPLE_ID=your@apple.com
NATIVEPHP_APPLE_APP_SPECIFIC_PASSWORD=xxxx-xxxx-xxxx-xxxx
NATIVEPHP_APPLE_TEAM_ID=ABCDE12345
```

Pipeline build akan secara otomatis:
1. Menandatangani semua biner dengan sertifikat Developer ID Anda.
2. Mengirimkan `.dmg` ke layanan notarisasi Apple.
3. Melampirkan tiket notarisasi ke `.dmg` sehingga dapat dibuka secara offline.

### Konfigurasi Build macOS

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

`entitlements.mac.plist` yang diperlukan untuk akses kamera:

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

## Build Lintas Platform

Electron Builder mendukung cross-compilation, tetapi dengan keterbatasan:

| Target build | Mesin build | Catatan |
|---|---|---|
| Windows `.exe` | Windows (direkomendasikan) | Juga memungkinkan di Linux melalui Wine |
| Windows `.exe` | macOS | Terbatas — tidak ada dukungan NSIS native |
| macOS `.dmg` | Hanya macOS | Apple memerlukan macOS untuk notarisasi |
| Linux `.AppImage` | Linux atau macOS/Windows melalui Docker | |

Untuk CI/CD, gunakan runner khusus platform (GitHub Actions `windows-latest`, `macos-latest`) — lihat contoh pipeline CI/CD.

---

## Strategi Distribusi

### Unduhan Langsung

Host installer di website Anda atau bucket S3. Pengguna mengunduh dan menjalankannya secara manual. Cocok untuk distribusi enterprise internal.

```
https://downloads.yourcompany.com/wedding-organizer/
  ├── Wedding-Organizer-Setup-1.0.0.exe      ← Installer Windows
  ├── Wedding-Organizer-1.0.0.dmg            ← Disk image macOS
  └── latest.yml                              ← Manifest auto-updater
```

### Pembaruan Otomatis (NativePHP Electron)

NativePHP Electron mengintegrasikan modul `autoUpdater` Electron. Terbitkan `latest.yml` (Windows) dan `latest-mac.yml` (macOS) bersama installer Anda, dan aplikasi akan memeriksa pembaruan saat startup.

```dotenv
# URL tempat NativePHP memeriksa pembaruan
NATIVEPHP_UPDATER_URL=https://downloads.yourcompany.com/wedding-organizer/
```

Pemeriksaan pembaruan manual di PHP:

```php
if (platform_feature('auto_updates')) {
    \Native\Laravel\Facades\Updater::check();
}
```

### Microsoft Store

Build paket MSIX untuk distribusi Microsoft Store:

```bash
php artisan native:build --os=win --target=appx
```

Memerlukan akun Microsoft Partner Center dan sertifikat penandatanganan kode yang dipercaya oleh Store.

### Mac App Store

Build Mac App Store memerlukan sertifikat `Mac App Store Distribution` terpisah dan menggunakan entitlement yang di-sandbox. Konsultasikan [dokumentasi NativePHP](https://nativephp.com) untuk konfigurasi khusus MAS.

---

## Verifikasi Pasca-Build

Setelah build, verifikasi paket sebelum distribusi:

```bash
# Konfirmasi manifest aset desktop ada
ls -la public/build/desktop/manifest.json

# Inspeksi bundel aplikasi yang dibangun (macOS)
codesign -dvv dist/Wedding-Organizer-1.0.0.dmg

# Verifikasi notarisasi (macOS)
spctl --assess --type open --context context:primary-signature -v dist/Wedding-Organizer-1.0.0.dmg

# Jalankan installer di mesin bersih untuk memverifikasi berfungsi tanpa dependensi pengembangan
```

---

## Dokumentasi Terkait

- [Kompilasi Aset](../asset-compilation.md) — cara kerja `npm run build:desktop`
- [Konfigurasi Lingkungan](../environment-configuration.md) — referensi variabel `.env.desktop`
- `.env.desktop.example` — file starter beranotasi
- [Contoh Pipeline CI/CD](../ci-cd.md) — alur kerja build otomatis untuk ketiga platform
