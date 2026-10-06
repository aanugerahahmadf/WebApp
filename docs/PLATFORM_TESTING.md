# Platform Testing

Langkah uji untuk delapan target runtime. Dipakai oleh
`php artisan platform:matrix`, yang mencetak matriks itu sendiri lalu
menunjuk ke dokumen ini untuk langkahnya.

Semua perintah di dokumen ini sudah dijalankan pada repo ini. Kalau ada perintah
yang gagal, itu bug di dokumen ini — bukan tanda perintahnya salah.

---

## 1. Lihat matriks dan platform yang terdeteksi

```bash
php artisan platform:matrix
```

Mencetak delapan key, label, dan apakah CBIR memakai kamera native atau
WebRTC:

```
| Key                 | Label                 | Web | Desktop | Native app | Mobile UI | CBIR camera |
| website_windows     | Website (Windows)     | yes | no      | no         | no        | webrtc      |
| desktop_app_windows | Desktop App (Windows) | no  | yes     | no         | no        | native      |
| mobile_app_android  | Mobile App (Android)  | no  | no      | yes        | yes       | native      |
```

```bash
php artisan platform:detect
```

Menampilkan nilai yang dipakai request ini: platform key, flag Web/Desktop/
Native, User-Agent, dan `CAPACITOR_PLATFORM`.

```bash
php artisan platform:status
```

Menampilkan mode platform yang aktif, runtime platform, file env yang
dipakai, direktori asset, serta fitur yang tersedia dan tidak tersedia:

```
Mode:             Web Server (web)
Runtime Platform: Website (Windows)
Environment File: .env.web
Asset Directory:  build/web
```

Untuk memastikan semua mode, jalankan tiap `serve:*` (§2) lalu ulangi
ketiga perintah itu.

---

## 2. Menjalankan tiap mode

Tiga mode dilayani oleh `php artisan serve` yang sama; yang berbeda hanya file
`.env.{mode}` dan direktori build yang dibaca. Port **wajib** diteruskan --
`serve:*` tidak punya default port sendiri, tanpa `--port` semuanya akan
menabrakkan port 8000.

```bash
php artisan serve:web     --port=8000   # .env.web     -> build/web
php artisan serve:mobile  --port=8001   # .env.mobile  -> build/mobile
php artisan serve:desktop --port=8002   # .env.desktop -> build/desktop
```

Aset tiap mode harus sudah di-build lebih dulu, kalau tidak halamannya
dirender tanpa CSS/JS:

```bash
npm run build:web       # -> public/build/web
npm run build:mobile    # -> public/build/mobile
npm run build:desktop   # -> public/build/desktop
# atau sekaligus:
npm run build:all
```

Setelah `platform:clear`, panggil `platform:status` lagi untuk memastikan
`Environment File` dan `Asset Directory` ikut berubah sesuai mode.

```bash
php artisan platform:clear
```

Membersihkan cache rute, config, view, dan cache aplikasi sekaligus, karena
deteksi platform ikut ter-cache di semuanya.

---

## 3. Menguji shell sungguhan

Shell Capacitor bukan bagian dari root repo; masing-masing punya folder,
`package.json`, dan konfigurasi sendiri. Semua perintah di bawah harus
dijalankan **dari dalam folder shell**_itulah satu-satunya tempat `npx cap`
dan `npm run android` tersedia.

```bash
cd app/Capacitor/UserApp     # atau app/Capacitor/AdminApp
```

| shell   | `appId`                        | panel  |
|---------|--------------------------------|--------|
| UserApp | `id.dekorasi.pengantin.user`    | `/user`  |
| AdminApp| `id.dekorasi.pengantin.admin`   | `/admin` |

Server yang dipanggil shell diatur lewat:

```bash
npm run set:url -- http://10.0.2.2:8001/welcome   # emulator Android
```

`10.0.2.2` adalah cara emulator Android mencapai mesin host; perangkat
fisik memakai IP LAN, dan produksi memakai domain. Nilai ini disimpan di
`capacitor.config.json` shell tersebut.

### Android

```bash
npm run sync         # build + cap sync
npm run android      # build + cap run android
```

### iOS (hanya di macOS)

```bash
npm run sync
npm run ios
```

### Desktop (Electron)

```bash
npm run desktop                                  # cap run electron
npx electron-builder --config electron/electron-builder.config.js --win
npx electron-builder --config electron/electron-builder.config.js --mac
```

Semua perintah ini memakai `electron/electron-builder.config.js` dan
`electron/tsconfig.json` yang ada di dalam folder shell.

---

## 4. Test suite

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --testsuite=Feature
```

Ada satu file yang bisa dijalankan sendiri, misalnya saat menelusuri satu
perilaku platform:

```bash
php artisan test tests/Unit/Platform/RuntimePlatformDetectorEnumTest/RuntimePlatformDetectorEnumTest.php
```

Detail pola dan batasannya ada di
[docs/pengujian/panduan-pengujian.md](pengujian/panduan-pengujian.md).

---

## 5. Dokumentasi terkait

- [Matriks Fitur Platform](platform-features/platform-features.md) —
  fitur mana yang aktif di tiap target, dan cara memanggilnya dari kode
- [Arsitektur Dukungan Platform](platform-support/platform-support.md) —
  komponen yang membaca sinyal platform
- [Strategi Konfigurasi Lingkungan](environment-configuration/environment-configuration.md) —
  file `.env.*` dan kapan yang mana dipakai
- [Proses Kompilasi Aset](asset-compilation/asset-compilation.md) —
  output `build/{web,mobile,desktop}` dan HMR