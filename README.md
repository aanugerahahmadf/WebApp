# 💍 Wedding Organizer — Dekorasi Bunga Pernikahan

Wedding Organizer adalah aplikasi multi-platform untuk perencanaan pernikahan dan
perdagangan dekorasi. Aplikasi ini menggabungkan katalog pelanggan, pesanan,
pembayaran, obrolan, notifikasi, keamanan akun, pencarian visual/CBIR, dan
panel administrasi Filament.

Aplikasi ini berjalan sebagai aplikasi web Laravel dan mendukung **Capacitor
Mobile** (Android/iOS) serta **Capacitor Electron** (Windows/macOS).

> Ini adalah repositori aplikasi. Jangan commit `.env`, data produksi, kunci
> privat, kredensial Firebase, kredensial pembayaran, rahasia OAuth, atau
> status lokal yang dihasilkan.

## Daftar Isi

- Fitur
- Teknologi
- Prasyarat
- Mulai cepat
- Konfigurasi
- Perintah pengembangan dan platform
- Layanan real-time
- Pengujian dan pemeriksaan kualitas
- Tata letak proyek
- Keamanan, deployment, kontribusi, dan lisensi

## Fitur

### Aplikasi pengguna

- Jelajahi produk dan paket layanan pernikahan.
- Cari katalog dan gunakan penemuan berbasis gambar/CBIR saat layanan AI yang
  dikonfigurasi tersedia.
- Simpan favorit, terapkan voucher, kelola keranjang, buat pesanan, dan ikuti
  aktivitas transaksi.
- Kelola detail profil, foto, alamat, bahasa pilihan, dan nomor WhatsApp.
- Pilih kode panggilan internasional dari selektor Filament yang dapat dicari
  dengan kode negara ISO, nama negara, dan kode panggilan.
- Gunakan pusat pesan untuk percakapan dan lampiran.
- Terima notifikasi database, broadcast, Firebase (mobile), dan desktop
  notifications (Electron) saat platform/layanan dikonfigurasi.
- Buka halaman detail notifikasi individual. Notifikasi keamanan dapat membuka
  aktivitas masuk sambil mempertahankan navigasi balik kontekstual.
- Gunakan perubahan kata sandi, konfirmasi OTP perubahan email, kontrol dua
  faktor, perangkat tepercaya, preferensi masuk tersimpan, kode cadangan, dan
  alat pemeriksaan akun.
- Ganti bahasa melalui pengalih bahasa. Teks statis yang menghadap pengguna
  harus menggunakan helper terjemahan Laravel.

### Administrasi

- Kelola pengguna, peran, izin, produk, paket, media, voucher, spanduk, ulasan,
  pesanan, dan transaksi.
- Gunakan tabel, formulir, aksi, filter Filament, dan widget dasbor untuk
  operasi harian.
- Kelola katalog dan data konten yang menghadap pelanggan.

### Platform yang didukung

| Platform | Kemampuan |
|---|---|
| Web | Aplikasi browser Laravel dengan aset Vite dan API browser yang didukung |
| Mobile | Capacitor Mobile shell (Android/iOS) — WebView memuat server Laravel |
| Desktop | Capacitor Electron shell (Windows/macOS) — WebView memuat server Laravel |

> **Catatan arsitektur:** Shell Capacitor hanyalah *WebView loader*. Tidak ada
> PHP tertanam, tidak ada bridge native, dan tidak ada database proxy. Semua
> logika bisnis berada di server Laravel; shell hanya menampilkan UI.

## Teknologi

| Area | Teknologi utama |
|---|---|
| Backend | PHP 8.3+, Laravel 12 |
| Antarmuka pengguna | Filament 3, Livewire 3, Blade, Tailwind CSS |
| Build front-end | Vite 7 dan Node.js |
| Database | MySQL secara default dengan migrasi/seeder Laravel |
| Real-time | Laravel Reverb, Echo, konfigurasi kompatibel Pusher |
| API dan autentikasi | Sesi Laravel dan Sanctum |
| Shell native | Capacitor Android, Capacitor iOS, Capacitor Electron |
| Media/dokumen | Spatie Media Library, Dompdf, PhpSpreadsheet |
| Integrasi | Firebase (FCM), Midtrans, OAuth Google, CBIR/AI |
| Alat kualitas | Pest, Laravel Pint, PHPStan, Rector |

Versi dependensi didefinisikan oleh `composer.json`, `composer.lock`,
`package.json`, dan lockfile-nya. Gunakan untuk audit dan deployment yang
dapat direproduksi.

## Prasyarat

| Prasyarat | Tujuan |
|---|---|
| PHP 8.3+ | Runtime Laravel; platform Composer diatur ke PHP 8.4.99 |
| Composer 2 | Instalasi dependensi PHP |
| Node.js LTS + npm | Tooling build Vite |
| MySQL | Database lokal default |
| Git | Clone, perbarui, dan berkontribusi |
| Redis (opsional) | Cache, antrean, atau layanan realtime berbasis Redis |
| Android SDK/JDK | Build shell Capacitor Android |
| Xcode | Build shell Capacitor iOS (hanya macOS) |

Fitur eksternal memerlukan kredensial dari penyedianya. Nilai di
`.env.example` adalah placeholder dan bukan rahasia produksi yang valid.

## Mulai Cepat

Jalankan perintah dari root repositori:

```bash
git clone https://github.com/aanugerahahmadf/Wedding-Organizer.git
cd Wedding-Organizer
composer install
npm install
```

Buat file environment dan kunci aplikasi:

```powershell
# Windows PowerShell
Copy-Item .env.example .env
php artisan key:generate
```

```bash
# macOS/Linux/Git Bash
cp .env.example .env
php artisan key:generate
```

Buat database MySQL yang dinamai oleh `DB_DATABASE`, atur kredensialnya di
`.env`, lalu jalankan:

```bash
php artisan migrate --seed
npm run build:web
php artisan serve:web --port=8000
```

Buka `http://127.0.0.1:8000`. Gunakan hanya akun yang dibuat secara lokal;
jangan pernah mempublikasikan atau menggunakan ulang kredensial produksi.

Untuk stack pengembangan lokal standar (server Laravel, pendengar antrean,
Vite), jalankan:

```bash
composer dev
```

## Konfigurasi

### File environment

| File | Kegunaan |
|---|---|
| `.env` | Konfigurasi dasar bersama |
| `.env.web` | Override khusus web opsional |
| `.env.mobile` | Override Capacitor Mobile opsional |
| `.env.desktop` | Override Capacitor Desktop opsional |

File platform ditumpuk di atas `.env`; nilai dalam file platform lebih diutamakan.

Minimal konfigurasikan URL, bahasa, zona waktu, dan database:

```dotenv
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=root
DB_PASSWORD=
```

Setelah mengedit konfigurasi produksi yang di-cache, jalankan:

```bash
php artisan config:clear
```

### Integrasi opsional

| Fitur | Pengaturan utama |
|---|---|
| Email dan OTP | `MAIL_*` |
| Broadcast Reverb | `REVERB_*`, `VITE_REVERB_*` |
| Broadcast kompatibel Pusher | `PUSHER_*` |
| Push Firebase (FCM) | `FIREBASE_*`, `FIREBASE_CREDENTIALS_PATH` |
| Pencarian gambar CBIR/AI | `AI_CORE_URL`, `CBIR_API_URL` |
| Masuk Google | `GOOGLE_*` |
| Midtrans | `MIDTRANS_*` |
| Build/penandatanganan Android | `ANDROID_*`, `JAVA_HOME` (di shell, bukan server) |

## Perintah Pengembangan dan Platform

### Web

Mulai Vite HMR di satu terminal:

```bash
npm run dev:web
```

Mulai Laravel di terminal lain:

```bash
php artisan serve:web --port=8000
```

Untuk build lokal yang dikompilasi:

```bash
npm run build:web
php artisan serve:web --port=8000
```

### Aset

| Perintah | Hasil |
|---|---|
| `npm run dev` | Server Vite default (web) |
| `npm run dev:web` | HMR Web |
| `npm run dev:mobile` | HMR Mobile |
| `npm run dev:desktop` | HMR Desktop |
| `npm run build` | Build Vite default (web) |
| `npm run build:web` | Bundle Web |
| `npm run build:mobile` | Bundle Mobile |
| `npm run build:desktop` | Bundle Desktop |
| `npm run build:all` | Build semua bundle target |

`public/build/` berisi aset yang dihasilkan. Commit hanya jika alur kerja
deployment memerlukan aset yang sudah dibangun sebelumnya.

### Target Shell Capacitor

| Target | Perintah mulai server | Perintah aset | Catatan |
|---|---|---|---|
| Web | `php artisan serve:web` | `npm run build:web` | Target browser |
| Android/iOS | `php artisan serve:mobile` | `npm run build:mobile` | Shell: `app/Capacitor/UserApp` atau `AdminApp` |
| Windows/macOS | `php artisan serve:desktop` | `npm run build:desktop` | Shell: `app/Capacitor/*/electron` |

Periksa atau reset status platform:

```bash
php artisan platform:status
php artisan platform:clear
```

Baca [docs/command-guide.md](docs/command-guide/command-guide.md) dan
[docs/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md)
sebelum menyiapkan target native.

### Build & Deploy Shell Capacitor

> Perintah di bawah dijalankan **di dalam folder shell**, bukan di root server.

**Mobile (Android):**
```bash
cd app/Capacitor/UserApp
npm run build
npx cap sync android
npx cap run android          # deploy ke emulator/device
npx cap run android --watch  # hot reload (dev)
```

**Mobile (iOS — hanya macOS):**
```bash
cd app/Capacitor/UserApp
npm run build
npx cap sync ios
npx cap run ios
```

**Desktop (Windows):**
```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --win
# Output: electron/dist/*.exe, *.zip
```

**Desktop (macOS):**
```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --mac
# Output: electron/dist/*.dmg, *.zip
```

**Set URL Shell (wajib sebelum build produksi):**
```bash
cd app/Capacitor/UserApp
npm run set:url -- https://dekorasi.example.com/user

cd app/Capacitor/AdminApp
npm run set:url -- https://dekorasi.example.com/admin
```

---

## Real-time, Antrean, dan Notifikasi

Jalankan worker saat `QUEUE_CONNECTION` bukan `sync`:

```bash
php artisan queue:listen --tries=1
```

Jalankan Reverb saat koneksi broadcast menggunakannya:

```bash
php artisan reverb:start
```

Jalankan tugas terjadwal secara lokal saat diaktifkan:

```bash
php artisan schedule:work
```

Alur notifikasi:

1. Sebuah event aplikasi membuat notifikasi database Filament.
2. Notifikasi melakukan broadcast ke klien aktif via WebSocket (semua platform).
3. FCM Push hanya dicoba untuk MobileApp* saat platform aktif dan
   konfigurasi layanan mengizinkannya.
4. Desktop Notifications via Web Notifications API untuk DesktopApp*.
5. Klik membuka `/user/notifications/{id}` untuk item yang dipilih.
6. Aktivitas login dapat membuka halaman keamanan yang sesuai.

---

## Pengujian dan Pemeriksaan Kualitas

Jalankan pemeriksaan yang relevan sebelum rilis:

```bash
composer test
php artisan test
./vendor/bin/pint --test
./vendor/bin/pint
./vendor/bin/phpstan analyse
php artisan view:clear
php artisan view:cache
npm run build:web
```

Di Windows PowerShell, gunakan `vendor\bin\pint.bat` dan
`vendor\bin\phpstan.bat` jika file executable Unix tidak tersedia.

---

## Tata Letak Proyek

| Path | Tujuan |
|---|---|
| `app/Filament/` | Halaman, resource, widget admin/pengguna |
| `app/Livewire/` | Komponen interaktif |
| `app/Models/` | Model Eloquent |
| `app/Services/` | Layanan domain dan integrasi |
| `app/Support/` | Helper bersama |
| `app/Console/Commands/ServePlatformCommand/` | `serve:web|mobile|desktop` |
| `app/Capacitor/` | Shell Capacitor (UserApp, AdminApp) |
| `config/` | Konfigurasi aplikasi |
| `database/` | Migrasi, factory, seeder |
| `docs/` | Panduan platform dan perintah |
| `lang/` | Terjemahan JSON/paket Laravel |
| `public/` | Titik masuk publik dan aset yang sudah dibangun |
| `public/build/{web,mobile,desktop}/` | Output Vite per platform |
| `resources/` | Blade, CSS, JavaScript |
| `routes/` | Rute web, API (tidak ada lagi routes/mobile, routes/desktop) |
| `tests/` | Pengujian Pest/Laravel |

---

## Keamanan dan Deployment

- Atur `APP_DEBUG=false`, gunakan HTTPS, cookie aman, dan `APP_KEY` produksi
  yang unik.
- Jangan pernah commit file environment, file kredensial Firebase, keystore,
  kata sandi SMTP, rahasia OAuth, kunci pembayaran, atau ekspor database.
- Tinjau peran, izin, kebijakan, dan akses admin sebelum deployment.
- Awasi worker antrean dan tugas terjadwal di produksi.
- Cadangkan database dan media sebelum migrasi atau rilis.
- Jaga lockfile Composer/Node tetap ter-commit untuk build yang dapat
  direproduksi.
- Bangun aset dan jalankan migrasi melalui pipeline deployment, lalu
  hapus/bangun ulang cache Laravel sesuai kebutuhan.

---

## Dokumentasi

| Dokumen | Deskripsi |
|---|---|
| [docs/command-guide.md](docs/command-guide/command-guide.md) | Perintah, prasyarat, pemecahan masalah |
| [docs/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md) | Pilih mode web, mobile, atau desktop |
| [docs/environment-configuration.md](docs/environment-configuration/environment-configuration.md) | Pelapisan environment |
| [docs/asset-compilation.md](docs/asset-compilation/asset-compilation.md) | Proses build Vite |
| [docs/platform-support.md](docs/platform-support/platform-support.md) | Arsitektur platform |
| [docs/platform-features.md](docs/platform-features/platform-features.md) | Matriks fitur per platform |
| [docs/deployment/mobile/mobile.md](docs/deployment/mobile/mobile.md) | Build APK/AAB/IPA |
| [docs/deployment/desktop/desktop.md](docs/deployment/desktop/desktop.md) | Build .exe/.dmg |

---

## Kontribusi

1. Buat branch yang terfokus dari branch default.
2. Jaga perubahan tetap terbatas dan hindari file/format yang tidak terkait
   yang dihasilkan secara otomatis.
3. Perbarui pengujian untuk perubahan perilaku.
4. Jalankan pemeriksaan PHP, Blade, dan aset yang relevan.
5. Jaga rahasia dan data pribadi agar tidak masuk ke commit.
6. Jelaskan perilaku yang terlihat pengguna dan verifikasi dalam pull request.

---

## Lisensi

Proyek ini dilisensikan di bawah [Lisensi MIT](LICENSE). Ketentuan hukum
lengkap ada di file [`LICENSE`](LICENSE) root.

Hak Cipta (c) 2026 Anugerah Ahmad.