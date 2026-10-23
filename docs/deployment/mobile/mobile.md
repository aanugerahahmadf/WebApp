# Deployment shell mobile (Android/iOS)

Shell mobile adalah WebView Capacitor. PHP, Laravel, database, dan logika bisnis
berada di server; APK/IPA hanya memuat URL server pada `capacitor.config.json`.

## Shell yang tersedia

| Shell | Panel | appId | URL produksi |
|---|---|---|---|
| `app/Capacitor/DekstopApp` | storefront + akun `welcome` | `id.dekorasi.pengantin.user` | `https://domain/home` |
| `app/Capacitor/DekstopAdminApp` | admin | `id.dekorasi.pengantin.admin` | `https://domain/admin` |

Nama folder `Dekstop` adalah nama aktual repository dan tidak boleh diganti tanpa
memperbarui workflow. Kedua shell memiliki `android/` dan `ios/`.

## Prasyarat

- Node.js LTS dan npm;
- Android Studio, Android SDK, JDK/Gradle untuk Android;
- macOS, Xcode, CocoaPods, dan Apple Developer account untuk iOS;
- server Laravel yang dapat dijangkau melalui HTTPS untuk rilis;
- konfigurasi database, storage, queue, broadcast, dan Firebase yang sudah diuji.

Build iOS hanya dapat dilakukan di macOS. HTTP lokal hanya untuk development.

## Siapkan server

Dari root repository:

```bash
composer install --no-dev --optimize-autoloader
npm ci
cp .env.mobile.example .env.mobile
# isi APP_URL, APP_KEY, database, mail, storage, broadcast, Firebase, dll.
npm run build:mobile
php artisan migrate --force
php artisan optimize
```

`npm run build:mobile` menghasilkan bundle di `public/build/mobile`. Manifest
biasanya berada di `public/build/mobile/manifest.json`; `PlatformAssetManager`
juga mendukung `.vite/manifest.json`.

Untuk development:

```bash
php artisan serve:mobile --host=0.0.0.0 --port=8001
```

Android emulator memakai `10.0.2.2`; device fisik memakai IP LAN komputer.

## Set URL shell

```bash
cd app/Capacitor/DekstopApp
npm install
npm run set:url -- https://dekorasi.example.com/home

cd ../DekstopAdminApp
npm install
npm run set:url -- https://dekorasi.example.com/admin
```

`set:url` memperbarui `capacitor.config.json.server.url` dan
`src/js/launcher.js` sekaligus. HTTPS mengatur `cleartext=false`; HTTP hanya
mengaktifkan cleartext untuk development.

## Android development

```bash
cd app/Capacitor/DekstopApp
npm run build
npx cap sync android
npx cap run android
npx cap run android -l       # live reload; bukan --watch
```

Untuk admin, jalankan dari `app/Capacitor/DekstopAdminApp`.

## Android release

```bash
cd app/Capacitor/DekstopApp
npm run build
npx cap sync android
cd android
gradlew.bat assembleRelease   # Windows
./gradlew assembleRelease     # macOS/Linux
./gradlew bundleRelease       # AAB untuk Play Store
```

Output standar ada di `android/app/build/outputs/apk/release/` atau
`android/app/build/outputs/bundle/release/`. Naikkan `versionCode`, atur signing,
dan jangan commit keystore atau `keystore.properties`.

## iOS

Hanya macOS:

```bash
cd app/Capacitor/DekstopApp
npm install
npm run build
npx cap sync ios
npx cap run ios
open ios/App/App.xcworkspace
```

Atur Team, signing, provisioning, bundle identifier, dan Build Number di Xcode.
Archive untuk TestFlight/App Store. Pastikan `NSCameraUsageDescription` ada jika
fitur kamera dipakai. Ulangi dari `DekstopAdminApp` untuk target admin.

## Kamera, auth, dan notifikasi

- bundle web memakai WebRTC;
- bundle mobile/desktop memakai hidden file input `capture` pada shell;
- login/session tetap ditangani Laravel;
- FCM memerlukan konfigurasi Firebase dan token user;
- broadcast memerlukan Reverb/Pusher dan WebSocket yang benar;
- secret server tidak boleh dimasukkan ke bundle.

## Checklist rilis

```text
? HTTPS, APP_DEBUG=false, dan APP_KEY produksi benar
? server.url menunjuk /home atau /admin yang benar
? build:mobile menghasilkan manifest dan asset yang dapat diakses
? appId, versionCode/build number, dan signing benar
? login, logout, intended URL, upload, checkout, dan chat diuji
? kamera diuji pada device nyata
? FCM/broadcast diuji bila diaktifkan
? kebijakan privasi dan akun review toko tersedia
? tidak ada .env, keystore, private key, atau credential dalam commit
```

## Verifikasi

```bash
curl -I https://dekorasi.example.com/home
curl -I https://dekorasi.example.com/api/health
php artisan platform:status
```

Jika halaman putih muncul, periksa URL shell, DNS/TLS, asset manifest, CSP, dan
log WebView sebelum membangun ulang APK.

## Referensi

- [Kompilasi aset](../../asset-compilation/asset-compilation.md)
- [Environment](../../environment-configuration/environment-configuration.md)
- [Dukungan platform](../../platform-support/platform-support.md)
- `app/Capacitor/DekstopApp/README.md`
