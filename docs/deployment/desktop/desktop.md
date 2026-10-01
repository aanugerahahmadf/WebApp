# Panduan Distribusi Aplikasi Desktop

Panduan ini mencakup proses membangun dan mendistribusikan aplikasi Wedding Organizer
CBIR sebagai aplikasi desktop native untuk Windows dan macOS menggunakan
[Capacitor Electron](https://capacitorjs.com/docs/electron).

---

## Arsitektur: shell Capacitor Electron

Aplikasi desktop ini **bukan** aplikasi yang menyematkan PHP. Sama seperti shell
mobile, ini adalah *WebView shell* yang memuat URL server Laravel Anda di dalam
Electron `BrowserWindow`.

```
┌─────────────────────────────────────────────┐
│  app/Capacitor/{UserApp,AdminApp}/electron  │
│  ├─ capacitor.config.json  → server.url     │
│  └─ src/js/launcher.js     → PANEL_URL      │
└──────────────┬──────────────────────────────┘
               │  https://domain-anda.com/welcome (atau /admin)
               ▼
┌─────────────────────────────────────────────┐
│  Server Laravel  (berada di server/host)    │
│  ├─ public/build/desktop  (bundle Vite)     │
│  ├─ /welcome → Filament panel (Storefront)  │
│  └─ /api     → REST API                     │
└─────────────────────────────────────────────┘
```

Konsekuensi:

- **Tidak ada PHP di dalam aplikasi.** `composer install` tidak perlu dijalankan
  di mesin build Electron; server Laravel berdiri sendiri.
- **Rilis aplikasi dan rilis server terpisah.** Anda dapat mem-build
  installer `.exe`/`.dmg` kapan saja tanpa men-deploy ulang server.
- **Semua fitur web bekerja.** WebRTC, WebSocket, File API, Notifications —
  karena Electron `BrowserView` mendukungnya semua, asalkan URLnya `https`.

---

## Prasyarat

| Persyaratan | Detail |
|---|---|
| Node.js 18+ | Untuk toolchain Electron, Vite, TypeScript |
| Electron 43+ | Sudah ada di `package.json` shell |
| **Build Windows:** Windows 10+ | Direkomendasikan native build |
| **Build macOS:** macOS 12+ + Xcode | Wajib untuk notarization & `.dmg` |
| Sertifikat Authenticode Windows | Opsional, untuk menghindari SmartScreen |
| Sertifikat Developer ID Apple | Wajib untuk distribusi macOS di luar App Store |
| Server Laravel publik | HTTPS, sudah ter-deploy |

> **Build macOS wajib di macOS.** Tidak ada jalur dari Windows/Linux karena
> notarization membutuhkan `xcrun notarytool`.

---

## Struktur Shell

Ada dua shell (satu per panel), keduanya memiliki folder `electron/`:

| Shell | Panel | appId | Path server |
|---|---|---|---|
| `app/Capacitor/UserApp` | Pelanggan (Storefront) | `id.dekorasi.pengantin.user` | `/welcome` |
| `app/Capacitor/AdminApp` | Admin | `id.dekorasi.pengantin.admin` | `/admin` |

Setiap shell memiliki proyek Electron sendiri di `electron/`:

```
app/Capacitor/UserApp/
├── capacitor.config.json
├── package.json
├── src/
│   └── js/launcher.js         # fallback loader untuk Electron
├── electron/
│   ├── package.json
│   ├── main.ts                # entry point Electron (tsc → build/main.js)
│   ├── electron-builder.config.js
│   └── icon.icns / icon.ico
└── dist/                      # output Vite build (webDir)
```

`capacitor.config.json` menentukan `server.url` yang digunakan Android/iOS.
Electron membaca `src/js/launcher.js` → `PANEL_URL` (di-update oleh
`npm run set:url`).

---

## Konfigurasi Lingkungan

Buat `.env.desktop` dari file contoh:

```bash
cp .env.desktop.example .env.desktop
```

Variabel yang dibaca di `.env.desktop`:

```dotenv
APP_ENV=production
APP_KEY=base64:...           # Buat dengan: php artisan key:generate
APP_URL=https://dekorasi.example.com

# Session file lokal pengguna tunggal ideal untuk desktop
SESSION_DRIVER=file
SESSION_LIFETIME=10080

# Memilih bundle public/build/desktop saat runtime
VITE_PLATFORM=desktop

# Cermin dari shell, untuk helper sisi PHP
CAPACITOR_USER_URL=https://dekorasi.example.com/user
CAPACITOR_ADMIN_URL=https://dekorasi.example.com/admin
CAPACITOR_USER_APP_ID=id.dekorasi.pengantin.user
CAPACITOR_ADMIN_APP_ID=id.dekorasi.pengantin.admin
```

### Referensi Variabel

| Variabel | Wajib | Deskripsi |
|---|---|---|
| `APP_ENV` | Ya | `production` untuk build distribusi |
| `APP_KEY` | Ya | Kunci enkripsi base64 32-byte |
| `APP_URL` | Ya | URL server produksi (`https`) |
| `SESSION_DRIVER` | Ya | `file` untuk desktop (single user) |
| `VITE_PLATFORM` | Ya | Harus `desktop` |
| `CAPACITOR_*_URL` | Tidak | Cermin `server.url`; default `APP_URL` |
| `CAPACITOR_*_APP_ID` | Tidak | Cermin `appId`; untuk deep link FCM |

> **Tidak ada lagi variabel `NATIVEPHP_*`.** Versi aplikasi, ikon, target
> build, dan penandatanganan dikonfigurasi **di dalam shell Electron** (
> `electron-builder.config.js`, `electron/package.json`), bukan di server
> Laravel.

---

## Menunjuk Shell ke Server Produksi

```bash
cd app/Capacitor/UserApp
npm install
npm run set:url -- https://dekorasi.example.com/user
```

Skrip ini menulis `capacitor.config.json` (Android/iOS) **dan**
`src/js/launcher.js` (Electron) sekaligus. Ulangi untuk AdminApp dengan
`/admin`.

---

## Langkah Build

### 1. Build aset di server (server-side)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build:desktop         # → public/build/desktop
php artisan optimize
php artisan migrate --force
```

### 2. Build aplikasi Electron (client-side)

Masuk ke shell, install deps Electron, build TypeScript, package dengan
`electron-builder`:

```bash
cd app/Capacitor/UserApp

# Install deps shell + Electron
npm install

# Build Vite bundle untuk desktop (sudah di server, tapi shell butuh dist/ lokal)
npm run build                 # → dist/

# Compile TypeScript entry point
npx tsc -p electron/tsconfig.json   # → electron/build/main.js

# Package
npx electron-builder --config electron/electron-builder.config.js
```

Output berada di `electron/dist/` (NSIS installer, portable zip, `.dmg`, dll.
tergantung target).

> `npx cap sync` **tidak diperlukan** untuk Electron; `electron-builder`
> langsung memaketkan `dist/` sebagai aset web.

---

## Packaging Windows

### Pengembangan

```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --win --dir
# --dir = unpacked folder, cepat untuk testing
```

### Installer `.exe` Produksi (NSIS)

```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --win
```

Output: `electron/dist/Wedding-Organizer-Setup-1.0.0.exe` (NSIS installer) dan
`electron/dist/Wedding-Organizer-1.0.0-win.zip` (portable).

### Penandatanganan Kode Windows (Authenticode)

Installer yang tidak ditandatangani memicu SmartScreen. Tandatangani dengan
sertifikat PFX:

```bash
# Di CI, sertifikat biasanya di base64-encode ke secret
export CSC_LINK="base64-encoded-pfx"
export CSC_KEY_PASSWORD="cert-password"

npx electron-builder --config electron/electron-builder.config.js --win
```

Atau gunakan `electron-builder` custom sign script (HSM cloud: Trusted Signing,
SignPath, Azure Key Vault).

### Konfigurasi Build Windows

Di `electron/electron-builder.config.js`:

```js
const config = {
  appId: 'id.dekorasi.pengantin.user',
  productName: 'Dekorasi User',
  win: {
    target: ['nsis', 'portable'],
    icon: 'electron/icon.ico',
    signAndEditExecutable: true,   // requires CSC_LINK
    rfc3161TimeStampServer: 'http://timestamp.digicert.com',
  },
  nsis: {
    oneClick: false,
    perMachine: false,
    allowToChangeInstallationDirectory: true,
  },
};
```

Versi ditentukan oleh `electron/package.json` → `version` (naikkan manual atau
via script CI).

---

## Packaging macOS

### Pengembangan

```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --mac --dir
```

### Produksi `.dmg` / `.zip`

```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --mac
```

Output: `electron/dist/Dekorasi User-1.0.0.dmg` dan
`electron/dist/Dekorasi User-1.0.0-mac.zip`.

### Penandatanganan Kode dan Notarisasi macOS

Wajib untuk distribusi di luar Mac App Store (Gatekeeper akan menolak
aplikasi tidak ditandatangani/notarisasi di macOS 10.15+).

Siapkan di environment CI:

```bash
export CSC_LINK="base64-encoded-p12"          # Developer ID Application cert
export CSC_KEY_PASSWORD="cert-password"
export APPLE_ID="your@apple.com"
export APPLE_APP_SPECIFIC_PASSWORD="xxxx-xxxx-xxxx-xxxx"
export APPLE_TEAM_ID="ABCDE12345"
```

`electron-builder` akan otomatis:

1. Menandatangani semua biner dengan sertifikat Developer ID (`codesign --deep`).
2. Mengirimkan `.dmg` ke layanan notarisasi Apple (`xcrun notarytool submit`).
3. Melampirkan tiket notarisasi (`xcrun stapler staple`) sehingga aplikasi
   dapat dibuka offline.

### Konfigurasi Build macOS

Di `electron/electron-builder.config.js`:

```js
mac: {
  target: ['dmg', 'zip'],
  icon: 'electron/icon.icns',
  entitlements: 'electron/entitlements.mac.plist',
  entitlementsInherit: 'electron/entitlements.mac.plist',
  hardenedRuntime: true,
  gatekeeperAssess: true,
  category: 'public.app-category.lifestyle',
  minimumSystemVersion: '12.0',
},
```

`entitlements.mac.plist` untuk akses kamera & file:

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

| Target build | Mesin build | Catatan |
|---|---|---|
| Windows `.exe` | Windows (direkomendasikan) | Linux via Wine/Docker terbatas |
| macOS `.dmg` | **Hanya macOS** | Notarisasi memerlukan macOS + Xcode |
| Linux `.AppImage` | Linux | Tidak dipakai proyek ini |

Untuk CI/CD, gunakan runner khusus platform (GitHub Actions
`windows-latest`, `macos-latest`). Lihat contoh pipeline CI/CD.

---

## Strategi Distribusi

### Unduh Langsung

Host installer di website/bucket S3. Cocok untuk enterprise internal:

```
https://downloads.dekorasi.example.com/
  ├── Dekorasi-User-Setup-1.0.0.exe
  ├── Dekorasi-User-1.0.0.dmg
  └── latest.yml / latest-mac.yml     ← manifest auto-updater
```

### Pembaruan Otomatis (electron-updater)

`electron-builder` menghasilkan `latest.yml` / `latest-mac.yml`. Taruh di
server unduh bersamaan dengan installer. Aplikasi memeriksa update saat
startup:

```js
// electron/main.ts
import { autoUpdater } from 'electron-updater';
autoUpdater.checkForUpdatesAndNotify();
```

Konfigurasi URL update di `electron-builder.config.js`:

```js
publish: {
  provider: 'generic',
  url: 'https://downloads.dekorasi.example.com/',
},
```

### Microsoft Store (MSIX)

```bash
npx electron-builder --config electron/electron-builder.config.js --win --target=msix
```

Memerlukan akun Microsoft Partner Center dan sertifikat Store-trusted.

### Mac App Store

Target terpisah (`mas`), sertifikat `Mac App Store Distribution`, entitlement
sandbox. Baca [dokumentasi Electron Forge](https://www.electronforge.io/config/plugins/maker-mas) atau [electron-builder MAS](https://www.electron.build/configuration/mas).

---

## Verifikasi Pasca-Build

```bash
# Bundle desktop benar-benar terbangun
ls -la public/build/desktop/.vite/manifest.json

# Server dapat dijangkau dan TLS-nya sehat
curl -I https://dekorasi.example.com/user
curl -I https://dekorasi.example.com/api/health

# Inspeksi installer Windows
# (di Windows) Right-click → Properties → Digital Signatures

# Verifikasi notarisasi macOS
spctl --assess --type open --context context:primary-signature -v \
  electron/dist/Dekorasi\ User-1.0.0.dmg

# Jalankan installer di mesin bersih untuk memverifikasi berfungsi tanpa
# dependensi pengembangan
```

---

## Dokumentasi Terkait

- [Panduan Mobile](../mobile/mobile.md) — shell Android/iOS
- [Kompilasi Aset](../asset-compilation.md) — cara kerja `npm run build:desktop`
- [Konfigurasi Lingkungan](../environment-configuration.md) — referensi `.env.desktop`
- [Dukungan Platform](../platform-support.md) — nilai `RuntimePlatform` per shell
- `.env.desktop.example` — file starter beranotasi
- `app/Capacitor/UserApp/electron/README.md` — dokumentasi shell Electron