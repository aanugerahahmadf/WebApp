# Panduan Deployment Aplikasi Mobile ke Toko Aplikasi

Panduan ini mencakup proses membangun dan menerbitkan aplikasi Wedding Organizer
CBIR untuk Android (Google Play Store) dan iOS (Apple App Store) menggunakan
[Capacitor](https://capacitorjs.com).

---

## Arsitektur: apa yang sebenarnya ada di dalam APK

Aplikasi ini **bukan** aplikasi native yang menyematkan PHP. Ia adalah *WebView
shell* — file APK/AAB hanya berisi activity Android (atau UIViewController iOS)
yang memuat URL server Laravel Anda di dalam WebView.

```
┌─────────────────────────────────────────────┐
│  app/Capacitor/UserApp        (shell)       │
│  ├─ capacitor.config.json  → server.url     │
│  └─ src/js/launcher.js     → PANEL_URL      │
└──────────────┬──────────────────────────────┘
               │  https://domain-anda.com/welcome
               ▼
┌─────────────────────────────────────────────┐
│  Server Laravel  (berada di server, bukan   │
│  di dalam aplikasi)                          │
│  ├─ public/build/mobile  (bundle Vite)      │
│  ├─ /welcome  → Filament panel (Storefront) │
│  └─ /api   → REST API                       │
└─────────────────────────────────────────────┘
```

Konsekuensi yang penting untuk deployment:

| Pertanyaan | Jawaban |
|---|---|
| Apakah perlu `composer install` di dalam app? | Tidak |
| Apakah PHP ikut ter-*bundle*? | Tidak |
| Apakah ada bridge native dari PHP? | Tidak |
| Di mana logika bisnis? | Di server Laravel, seperti biasa |
| Apa yang menentukan tampilan? | Bundle `public/build/mobile` yang dibangun di server |
| Apa yang menentukan URL tujuan? | `capacitor.config.json` → `server.url` |

Karena tidak ada PHP di dalam aplikasi, **rilis aplikasi dan rilis server adalah
dua hal terpisah**. Anda dapat menerbitkan app ke toko kapan saja tanpa
membangun ulang server, dan sebaliknya.

> **Konsekuensi operasional yang paling penting:** viewfinder WebRTC (kamera
> langsung di halaman) hanya berfungsi pada *secure context*. Aplikasi ini
> memakai `androidScheme`/`iosScheme` = `https` untuk produksi, sehingga
> WebView aman dan kamera dapat dipakai. Pada mode pengembangan dengan
> `http://10.0.2.2`, kamera menggunakan input file tersembunyi sebagai
> gantinya (lihat bagian [Kamera](#kamera)).

---

## Prasyarat

| Persyaratan | Detail |
|---|---|
| Node.js 18+ | Untuk build shell Capacitor dan aset Laravel |
| Android Studio + Android SDK 36 | Untuk build Android |
| JDK 17+ | Diperlukan oleh Gradle |
| Xcode 15+ | Untuk build iOS (**hanya macOS**) |
| Akun Apple Developer | Untuk pengiriman ke App Store iOS |
| Akun Google Play Developer | Untuk pengiriman ke Play Store Android |
| Server Laravel yang dapat dijangkau publik | HTTPS, sudah ter-deploy |

> Build iOS **wajib** di macOS. Tidak ada jalur dari Windows atau Linux.

---

## Struktur Shell

Ada dua shell, satu per panel:

| Shell | Panel | appId | Path server |
|---|---|---|---|
| `app/Capacitor/UserApp` | Pelanggan (Storefront) | `id.dekorasi.pengantin.user` | `/welcome` |
| `app/Capacitor/AdminApp` | Admin | `id.dekorasi.pengantin.admin` | `/admin` |

Keduanya berisi proyek native (`android/`, `ios/`, `electron/`) dan bundle
shell-nya sendiri (`src/`, `dist/`). Yang **tidak** ada di dalamnya adalah
kode aplikasi Anda — semuanya dilayani server.

### `capacitor.config.json`

```json
{
  "appId": "id.dekorasi.pengantin.user",
  "appName": "Dekorasi User",
  "webDir": "dist",
  "server": {
    "url": "https://dekorasi.example.com/welcome",
    "androidScheme": "https",
    "iosScheme": "https",
    "cleartext": false
  },
  "plugins": {
    "SplashScreen": {
      "launchAutoHide": true,
      "backgroundColor": "#eab308"
    }
  }
}
```

Nilai `server.url` ditulis oleh `npm run set:url` (lihat di bawah). Skrip itu
sekaligus menyelaraskan `androidScheme`/`iosScheme`/`cleartext` dengan skema
URL: `http` → cleartext diizinkan (khusus pengembangan), `https` → keduanya
`https`.

---

## Konfigurasi Lingkungan

Buat `.env.mobile` dari file contoh:

```bash
cp .env.mobile.example .env.mobile
```

Variabel yang benar-benar dibaca di `.env.mobile`:

```dotenv
APP_ENV=production
APP_KEY=base64:...          # Buat dengan: php artisan key:generate
APP_URL=https://dekorasi.example.com

# Session — driver database bertahan saat aplikasi di-restart
SESSION_DRIVER=database
SESSION_LIFETIME=10080

# Memilih bundle public/build/mobile saat runtime
VITE_PLATFORM=mobile

# Cermin dari shell, untuk helper sisi PHP
CAPACITOR_USER_URL=https://dekorasi.example.com
CAPACITOR_USER_APP_ID=id.dekorasi.pengantin.user
```

### Referensi Variabel

| Variabel | Wajib | Deskripsi |
|---|---|---|
| `APP_ENV` | Ya | `production` untuk build toko |
| `APP_KEY` | Ya | Kunci enkripsi base64 32-byte |
| `APP_URL` | Ya | URL server, harus `https` di produksi |
| `SESSION_DRIVER` | Ya | `database` agar session bertahan saat app restart |
| `VITE_PLATFORM` | Ya | Harus `mobile` |
| `CAPACITOR_USER_URL` | Tidak | Cermin `server.url`; default ke `APP_URL` |
| `CAPACITOR_USER_APP_ID` | Tidak | Cermin `appId`; untuk deep link notifikasi |

> **Tidak ada lagi variabel `NATIVEPHP_*`.** App id, versi, dan build number
> berada di `capacitor.config.json` (untuk shell) dan
> `android/app/build.gradle` / `Info.plist` (untuk native). Server tidak
> membutuhkannya.

`CAPACITOR_*` hanya cermin supaya helper sisi PHP bisa membangun URL absolut
tanpa membaca file shell. Nilainya harus selalu sama dengan isi
`capacitor.config.json`.

---

## Menunjuk Shell ke Server Anda

Selalu gunakan skrip ini, jangan edit `server.url` dan `PANEL_URL` secara
manual — keduanya harus berubah bersama.

```bash
cd app/Capacitor/UserApp
npm install
npm run set:url -- https://dekorasi.example.com/user
```

Skrip `scripts/set-url.mjs` menulis ke dua tempat sekaligus:

- `capacitor.config.json` → `server.url` (dipakai Android & iOS)
- `src/js/launcher.js` → `PANEL_URL` (dipakai fallback Electron)

Ulangi untuk `AdminApp` dengan path `/admin`.

---

## Langkah Build

### 1. Build aset di server

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build:mobile          # → public/build/mobile
php artisan optimize
php artisan migrate --force
```

### 2. Sync shell Capacitor

Setiap perubahan pada `webDir` shell atau daftar plugin memerlukan sync:

```bash
cd app/Capacitor/UserApp
npm run sync                  # = npm run build && npx cap sync
```

`cap sync` menyalin `dist/` ke `android/app/src/main/assets/public/` dan
memperbarui `capacitor.config.json` yang tertanam di
`android/app/src/main/assets/capacitor.config.json`.

### 3. Jalankan ke perangkat (dev)

```bash
npm run android               # = npm run build && npx cap run android
```

---

## Build Android

### Build Pengembangan

```bash
cd app/Capacitor/UserApp
npm run build
npx cap sync android
npx cap run android                    # deploy ke perangkat/emulator terhubung
```

### Release Build (APK)

```bash
cd app/Capacitor/UserApp/android
./gradlew assembleRelease
# Windows:  gradlew.bat assembleRelease
```

Output: `app/build/outputs/apk/release/app-release.apk`

### App Bundle (Play Store)

Play Store memerlukan Android App Bundle (`.aab`):

```bash
cd app/Capacitor/UserApp/android
./gradlew bundleRelease
```

Output: `app/build/outputs/bundle/release/app-release.aab`

### Keystore dan Penandatanganan

Buat keystore sekali dan simpan dengan aman — kehilangan file **dan** kata
sandi berarti Anda tidak dapat memperbarui aplikasi selamanya.

```bash
keytool -genkey -v \
  -keystore wedding-organizer-release.jks \
  -alias wedding-organizer \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

Buat `android/keystore.properties` (jangan di-commit):

```properties
storeFile=/path/to/wedding-organizer-release.jks
storePassword=your-keystore-password
keyAlias=wedding-organizer
keyPassword=your-key-password
```

Lalu tambahkan blok `signingConfigs` dan rangkai ke `buildTypes.release` di
`android/app/build.gradle`:

```groovy
android {
    signingConfigs {
        release {
            def props = new Properties()
            props.load(new FileInputStream(rootProject.file('keystore.properties')))
            storeFile     file(props['storeFile'])
            storePassword props['storePassword']
            keyAlias      props['keyAlias']
            keyPassword   props['keyPassword']
        }
    }
    buildTypes {
        release { signingConfig signingConfigs.release }
    }
}
```

### Versi dan SDK level

`versionCode` harus **meningkat** pada setiap rilis yang diunggah. Edit
`android/app/build.gradle`:

```groovy
defaultConfig {
    applicationId "id.dekorasi.pengantin.user"
    versionCode 2        // naik setiap rilis
    versionName "1.0"    // tampil di Play Store
}
```

Level SDK ada di `android/variables.gradle`:

```groovy
minSdkVersion    = 24   // Android 7.0
compileSdkVersion = 36
targetSdkVersion = 36
```

> `targetSdkVersion` harus memenuhi persyaratan Play Store saat ini. Naikkan
> `compileSdkVersion`/`targetSdkVersion` setiap kali Play memperbarui
> persyaratan level API.

### Izin Android

Kamera sudah dideklarasikan di `android/app/src/main/AndroidManifest.xml`. Untuk
Notifikasi (FCM) pada Android 13+, tambahkan:

```xml
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

---

## Build iOS

> **Build iOS memerlukan macOS.**

### Build Pengembangan

```bash
cd app/Capacitor/UserApp
npm run build
npx cap sync ios
npx cap run ios
```

Buka proyek native dengan Xcode jika diperlukan:

```bash
open ios/App/App.xcworkspace
```

### Release Build

```bash
cd app/Capacitor/UserApp
npx cap build ios --release
```

Atau lewat Xcode: **Product → Archive**. Hasilnya `.ipa`/`xcarchive` untuk
TestFlight atau App Store.

### Penandatanganan Kode iOS

1. Xcode → Settings → Accounts → tambahkan Apple ID Anda.
2. Pilih target `App`, atur **Team**, dan pastikan **Bundle Identifier** =
   `appId` di `capacitor.config.json`.
3. Aktifkan *Automatically manage signing*, atau pilih sertifikat distribusi dan
   provisioning profile secara manual.
4. Naikkan **Build** (build number) di *Signing & Capabilities* pada setiap
   rilis yang diunggah.

### Konfigurasi iOS

- **Deployment target:** atur di Xcode, target proyek `App`.
- **Izin kamera:** `Info.plist` harus memuat
  `NSCameraUsageDescription` (dipakai saat WebRTC / input file kamera dibuka).
- **ATS:** untuk URL `https` tidak perlu pengecualian. Scheme `https` di
  `capacitor.config.json` sudah memastikan WebView diperlakukan sebagai
  konteks aman.

---

## Kamera

Perilaku kamera ditentukan oleh **bundle mana yang sedang berjalan**, bukan
oleh runtime flag:

| Bundle | Perilaku |
|---|---|
| `build/web` | Viewfinder WebRTC langsung (`getUserMedia`) |
| `build/mobile`, `build/desktop` | Shell membuka `<input type="file" capture>` |

Alurnya di sisi server: `CbirSearchPage` mendispatch event Alpine
`capacitor-camera-open-input`, dan partial
`resources/views/{User,Welcome}/components/cbir-camera-options`
memiliki listener yang membuka input file yang tersembunyi. Listener JS
memilih kamera depan/belakang sesuai tombol yang diklik.

Viewfinder WebRTC tetap dapat dipakai di shell selama halaman berada pada
konteks aman (`https`), yang memang dikonfigurasi untuk produksi.

---

## Pengiriman ke Toko Aplikasi

### Google Play Store

1. Buat aplikasi di [Google Play Console](https://play.google.com/console).
2. Aktifkan **Play App Signing** lalu unggah keystore rilis Anda.
3. Unggah `.aab` ke track yang diinginkan (Internal → Alpha → Beta → Produksi).
4. Lengkapi Daftar Toko: screenshot, deskripsi, rating konten, URL privasi.
5. Kirim untuk ditinjau.

**Daftar periksa rilis:**

```
☐ APP_ENV=production di .env.mobile
☐ server.url di capacitor.config.json = https://domain-produksi (bukan http://10.0.2.2)
☐ cleartext = false
☐ versionCode di android/app/build.gradle ditingkatkan
☐ AAB ditandatangani dengan keystore rilis
☐ Target SDK memenuhi persyaratan Play Store saat ini
☐ URL kebijakan privasi diatur
☐ Minimal 2 screenshot per tipe perangkat
☐ Kuesioner rating konten dilengkapi
```

### Apple App Store

1. Buat catatan aplikasi di [App Store Connect](https://appstoreconnect.apple.com).
2. Archive di Xcode (`npx cap build ios --release` atau Product → Archive).
3. Unggah ke App Store Connect melalui Xcode Organizer.
4. Lengkapi daftar App Store: screenshot, deskripsi, kata kunci, URL dukungan.
5. Kirim untuk App Review.

**Daftar periksa rilis:**

```
☐ APP_ENV=production di .env.mobile
☐ server.url di capacitor.config.json = https://domain-produksi
☐ Build number di Xcode ditingkatkan
☐ Ditandatangani dengan sertifikat Distribution + provisioning profile
☐ NSCameraUsageDescription diisi di Info.plist
☐ URL kebijakan privasi diatur
☐ Screenshot untuk iPhone 6.5", iPhone 5.5", iPad Pro 12.9"
☐ Informasi App Review: kredensial akun demo diberikan
```

---

## Verifikasi Pasca-Deployment

```bash
# Bundle mobile benar-benar terbangun
ls -la public/build/mobile/.vite/manifest.json

# Server dapat dijangkau dan TLS-nya sehat
curl -I https://dekorasi.example.com/user
curl -I https://dekorasi.example.com/api/health

# Cerminan env tidak menyimpang dari shell
php artisan tinker --execute="print(config('app-platform.shells'));"
```

Lalu jalankan aplikasi dan pastikan:

- Halaman panel `/user` termuat di dalam WebView (bukan putih kosong).
- Login berfungsi dan session bertahan setelah app ditutup.
- Kamera bisa mengambil gambar (input file di shell).
- Notifikasi masuk saat app foreground (WebSocket) dan saat background (FCM).

---

## Rollback Rilis

- **Google Play:** Hentikan rollout dari Play Console → Release → Production →
  Manage rollout → Halt.
- **Apple App Store:** versi yang sudah disetujui tidak dapat dihapus. Siapkan
  rilis hotfix dan kirimkan untuk expedited review.
- **Server:** karena app hanyalah pemuat URL, memperbaiki server langsung
  memperbaiki semua versi aplikasi yang sudah terpasang — tanpa update
> pengguna. Ini keunggulan utama arsitektur shell dibanding aplikasi native
> penuh.

---

## Dokumentasi Terkait

- [Panduan Desktop](../desktop/desktop.md) — shell Electron untuk Windows/macOS
- [Kompilasi Aset](../../asset-compilation/asset-compilation.md) — cara kerja `npm run build:mobile`
- [Konfigurasi Lingkungan](../../environment-configuration/environment-configuration.md) — referensi `.env.mobile`
- [Dukungan Platform](../../platform-support/platform-support.md) — nilai `RuntimePlatform` per shell
- `.env.mobile.example` — file starter beranotasi
- `app/Capacitor/UserApp/README.md` — dokumentasi shell