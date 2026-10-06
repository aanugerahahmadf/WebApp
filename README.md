# 💍 Wedding Organizer — Dekorasi Bunga Pernikahan

Aplikasi multi-platform untuk perencanaan pernikahan dan perdagangan dekorasi.
Menggabungkan katalog pelanggan, pesanan, pembayaran, obrolan, notifikasi,
keamanan akun, pencarian visual/CBIR, panel administrasi Filament, dan PWA.

Berjalan sebagai aplikasi web Laravel, dengan **Capacitor Mobile**
(Android/iOS) dan **Capacitor Electron** (Windows/macOS) sebagai shell.

> Ini adalah repositori aplikasi. Jangan commit `.env`, data produksi, kunci
> privat, kredensial Firebase, kredensial pembayaran, rahasia OAuth, atau
> status lokal yang dihasilkan.

## Daftar Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Prasyarat](#prasyarat)
- [Mulai Cepat](#mulai-cepat)
- [Konfigurasi](#konfigurasi)
- [Perintah Pengembangan dan Platform](#perintah-pengembangan-dan-platform)
- [Semua Perintah Artisan](#semua-perintah-artisan)
- [Integrasi AI Core](#integrasi-ai-core)
- [Real-time, Antrean, dan Notifikasi](#real-time-antrean-dan-notifikasi)
- [Pengujian dan Pemeriksaan Kualitas](#pengujian-dan-pemeriksaan-kualitas)
- [Tata Letak Proyek](#tata-letak-proyek)
- [Keamanan, Deployment, Kontribusi, dan Lisensi](#keamanan-deployment-kontribusi-dan-lisensi)
- [Dokumentasi](#dokumentasi)
- [Masalah yang Sering Muncul](#masalah-yang-sering-muncul)

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
- Gunakan pusat pesan untuk percakapan dan lampiran (gambar, video, dokumen).
- Terima notifikasi database, broadcast, Firebase (mobile), dan desktop
  notifications (Electron) saat platform/layanan dikonfigurasi.
- Buka halaman detail notifikasi individual. Notifikasi keamanan dapat membuka
  aktivitas masuk sambil mempertahankan navigasi balik kontekstual.
- Gunakan perubahan kata sandi, konfirmasi OTP perubahan email, kontrol dua
  faktor (TOTP), perangkat tepercaya, preferensi masuk tersimpan, kode cadangan,
  dan alat pemeriksaan akun.
- Verifikasi identitas dengan dokumen (KTP) dan pencocokan wajah.
- Ganti bahasa melalui pengalih bahasa (Indonesia/Inggris).

### Administrasi

- Kelola pengguna, peran, izin, produk, paket, media, voucher, spanduk,
  ulasan, pesanan, dan transaksi.
- Gunakan tabel, formulir, aksi, filter Filament, dan widget dasbor untuk
  operasi harian.
- Kelola katalog dan data konten yang menghadap pelanggan.
- Ekspor data (PDF/XLSX) dan kelola pembayaran BRI serta Midtrans.

### Platform yang didukung

| Platform | Kemampuan |
|---|---|
| Web | Aplikasi browser Laravel dengan aset Vite dan REST API |
| Mobile | Capacitor Mobile shell (Android/iOS) — WebView memuat server Laravel |
| Desktop | Capacitor Electron shell (Windows/macOS) — WebView memuat server Laravel |
| PWA | Manifest + service worker; aktif lewat `PLATFORM_PWA_ENABLED` |

> **Catatan arsitektur:** Shell Capacitor hanyalah *WebView loader*. Tidak ada
> PHP tertanam, tidak ada bridge native, dan tidak ada database proxy. Semua
> logika bisnis berada di server Laravel; shell hanya menampilkan UI.

## Teknologi

| Area | Teknologi utama |
|---|---|
| Backend | PHP `^8.3`, Laravel `^12.0` |
| Antarmuka pengguna | Filament `^3.3`, Livewire `^3.7`, Blade, Tailwind CSS `^4.0` |
| Build front-end | Vite `^7.0.7` dan Node.js |
| Database | MySQL secara default dengan migrasi/seeder Laravel |
| Real-time | Laravel Reverb `^1.7`, Echo, konfigurasi kompatibel Pusher |
| API dan autentikasi | Sesi Laravel dan Sanctum `^4.0` |
| Shell native | Capacitor Android, Capacitor iOS, Capacitor Electron |
| Media/dokumen | Spatie Media Library, Dompdf, PhpSpreadsheet |
| Integrasi | Firebase (FCM), Midtrans, BRI, OAuth Google, CBIR/AI Core |
| Alat kualitas | Pest, Laravel Pint, PHPStan, Rector |

Versi dependency didefinisikan oleh `composer.json`, `composer.lock`,
`package.json`, dan lockfile-nya. Gunakan untuk audit dan deployment yang
dapat direproduksi.

## Prasyarat

| Prasyarat | Tujuan |
|---|---|
| PHP 8.3+ | Runtime Laravel |
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
git clone https://github.com/aanugerahahmadf/WebApp.git
cd WebApp
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

Buat akun admin pertama:

```bash
php artisan app:init-admin admin@example.com "PasswordKuat123" "Admin"
```

Untuk stack pengembangan lokal standar (server Laravel, pendengar antrean,
Vite), jalankan:

```bash
composer dev
```

## Konfigurasi

### File environment

| File | Kegunaan |
|---|---|
| `.env` | Konfigurasi dasar bersama — wajib |
| `.env.web` | Override khusus web — opsional |
| `.env.mobile` | Override Capacitor Mobile — opsional |
| `.env.desktop` | Override Capacitor Desktop — opsional |
| `.env.example` | Template untuk `.env` |
| `.env.web.example` | Template untuk `.env.web` |
| `.env.mobile.example` | Template untuk `.env.mobile` |
| `.env.desktop.example` | Template untuk `.env.desktop` |

File platform ditumpuk di atas `.env`; nilai dalam file platform lebih diutamakan.
Kalau `.env.{mode}` tidak ada, dilewati diam-diam — jadi hanya `.env` yang
wajib. Salin dari `.{mode}.example` saat ingin mengunci setting per platform:

```bash
cp .env.mobile.example .env.mobile
```

Port default tiap platform ada di file example tersebut:

| Mode | Port | `APP_URL` di example |
|---|---|---|
| Web | 8000 | `http://localhost:8000` |
| Mobile | 8001 | `http://10.0.2.2:8001` |
| Desktop | 8002 | `http://localhost:8002` |

> `10.0.2.2` adalah cara emulator Android mencapai mesin host. Perangkat
> fisik memakai IP LAN.

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
| Masuk Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` |
| Google Sheets (cadangan ulasan) | `GOOGLE_SHEETS_*` |
| Midtrans | `MIDTRANS_*` |
| BRI (virtual account/QRIS) | `MIDTRANS_SNAP_BI_*` |
| PWA | `PLATFORM_PWA_ENABLED`, `PLATFORM_PWA_START_URL` |
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

> `--port` **wajib** diteruskan. `serve:web`/`serve:mobile`/`serve:desktop`
> tidak punya default port sendiri — semuanya melewatkan ke `php artisan
> serve`, yang default-nya 8000. Tanpa `--port`, ketiga mode akan berebut
> port yang sama.

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
| `npm run build:main` | Bundle utama (`public/build/manifest.json`) |

`public/build/{web,mobile,desktop}/` adalah output Vite per platform. Yang
dibaca Laravel adalah `public/build/manifest.json` — jadi `npm run build:main`
adalah perintah yang relevan untuk deployment web.

### Target Shell Capacitor

| Target | Perintah mulai server | Perintah aset | Shell |
|---|---|---|---|
| Web | `php artisan serve:web` | `npm run build:web` | — (browser) |
| Android/iOS | `php artisan serve:mobile` | `npm run build:mobile` | `app/Capacitor/UserApp` atau `AdminApp` |
| Windows/macOS | `php artisan serve:desktop` | `npm run build:desktop` | `app/Capacitor/*/electron` |

Periksa atau reset status platform:

```bash
php artisan platform:status
php artisan platform:matrix
php artisan platform:detect
php artisan platform:clear
```

Untuk menguji tampilan shell tanpa membangunnya, paksa platform lewat header:

```bash
curl -s -H "X-Shell: android" http://127.0.0.1:8000/welcome/home | grep 'slug:'
curl -s -H "X-Shell: desktop" http://127.0.0.1:8000/welcome/home | grep 'slug:'
```

Baca [docs/platform-testing/platform-testing.md](docs/platform-testing/platform-testing.md),
[docs/command-guide.md](docs/command-guide/command-guide.md), dan
[docs/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md)
sebelum menyiapkan target native.

### Build & Deploy Shell Capacitor

> **Semua perintah di bawah dijalankan di dalam folder shell**, bukan di root
> server. `npm run android`, `npm run sync`, `npm run set:url`, dan `npx cap`
> hanya ada di `app/Capacitor/{UserApp,AdminApp}/package.json`.

Script yang tersedia di dalam folder shell:

| Script | Equivalent |
|---|---|
| `npm run build` | `vite build` |
| `npm run sync` | `npm run build && cap sync` |
| `npm run android` | `npm run build && cap run android` |
| `npm run ios` | `npm run build && cap run ios` |
| `npm run desktop` | `npm run build && cap run electron` |
| `npm run set:url -- <url>` | Menulis `server.url` di `capacitor.config.json` |

**Mobile (Android):**
```bash
cd app/Capacitor/UserApp
npm run sync
npm run android
npx cap run android -l          # live reload (dev)
```

Opsi live-reload milik Capacitor CLI: `-l`/`--live-reload`, `--host`,
`--port`, `--forwardPorts`, `--https`. **Tidak ada opsi `--watch`.**

**Mobile (iOS — hanya macOS):**
```bash
cd app/Capacitor/UserApp
npm run sync
npm run ios
npx cap run ios -l
```

**Desktop (Windows):**
```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --win
# Output: app/Capacitor/UserApp/electron/dist/*.exe, *.zip
```

**Desktop (macOS):**
```bash
cd app/Capacitor/UserApp
npm run build
npx tsc -p electron/tsconfig.json
npx electron-builder --config electron/electron-builder.config.js --mac
# Output: app/Capacitor/UserApp/electron/dist/*.dmg, *.zip
```

Untuk paket distributable tanpa penginstall (cek cepat):
`--win --dir` atau `--mac --dir`. Untuk MSIX: `--win --target=msix`.

**Set URL Shell (wajib sebelum build produksi):**
```bash
cd app/Capacitor/UserApp
npm run set:url -- https://dekorasi.example.com/user

cd app/Capacitor/AdminApp
npm run set:url -- https://dekorasi.example.com/admin
```

`appId` masing-masing shell: `id.dekorasi.pengantin.user` dan
`id.dekorasi.pengantin.admin`.

---

## Semua Perintah Artisan

Perintah bawaan Laravel (`migrate`, `test`, `optimize`, `queue:*`, `schedule:*`,
`storage:*`) tersedia seperti biasa. Berikut perintah milik aplikasi ini:

### Platform dan server

| Perintah | Fungsi |
|---|---|
| `serve:web` | Serve dalam mode Web (meneruskan ke `php artisan serve`) |
| `serve:mobile` | Serve dalam mode Mobile |
| `serve:desktop` | Serve dalam mode Desktop |
| `platform:status` | Mode aktif, runtime platform, file env, direktori asset, fitur |
| `platform:detect` | Nilai deteksi platform untuk request ini |
| `platform:matrix` | Matriks 8 target runtime |
| `platform:clear` | Bersihkan cache rute, config, view, dan aplikasi |

### AI dan CBIR

| Perintah | Fungsi |
|---|---|
| `cbir:check` | Periksa koneksi dan health AI Core |
| `cbir:sync` | Sinkronkan CSV CBIR ke AI Core |
| `ai:sync` | Sinkronkan dataset ke AI Core |

### Operasional

| Perintah | Fungsi |
|---|---|
| `app:init-admin {email} {password} {name}` | Buat akun admin pertama |
| `app:install` | Instalasi/penyiapan awal aplikasi |
| `app:daily-summary` | Ringkasan harian |
| `app:update-order-status` | Perbarui status pesanan |
| `app:prune-storage` | Pangkas penyimpanan |
| `app:clear-logs` | Bersihkan log |
| `app:sync-bri-payments` | Sinkronkan pembayaran BRI |
| `user:sync-kyc-keys` | Sinkronkan kunci KYC pengguna |
| `reviews:backup-sheet` | Cadangkan ulasan ke Google Sheets |
| `reviews:prune-photos` | Pangkas foto ulasan |
| `media:prune` | Pangkas media |
| `storage:inventory` | Inventaris penyimpanan |
| `db:generate-seed-images` | Buat gambar contoh untuk seeder |

### Firebase dan terjemahan

| Perintah | Fungsi |
|---|---|
| `firestore:test` | Uji koneksi Firestore |
| `lang:generate-flutter-arb {flutterPath?}` | Ekspor terjemahan ke `.arb` untuk app Flutter |
| `lang:sync-json --force` | Sinkronkan kunci terjemahan JSON |

---

## Integrasi AI Core

Pencarian gambar/CBIR, verifikasi wajah, dan OCR berjalan sebagai **layanan
terpisah**, bukan di dalam proses PHP. Server ini memanggilnya
lewat HTTP:

```dotenv
AI_CORE_URL=http://127.0.0.1:5000
CBIR_API_URL=http://127.0.0.1:5000
```

Periksa koneksinya:

```bash
php artisan cbir:check
curl -s http://127.0.0.1:5000/health
```

Saat `AI_CORE_URL` tidak terjangkau, panel tetap berfungsi — fitur yang
bergantung pada AI dilewati, bukan membuat halaman error.

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

## Pengujian dan Pemeriksaan Kualitas

Tiga suite terdaftar di `phpunit.xml`: `Unit`, `Integration`, `Feature`.

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --testsuite=Feature
php artisan test                                        # seluruh suite
php artisan test tests/Feature/Shared/CbirGalleryIndexingTest/CbirGalleryIndexingTest.php
php artisan test --filter="nama test"
```

`composer test` menjalankan `config:clear` lalu `php artisan test`.

Pemeriksaan kualitas:

```bash
./vendor/bin/pint --test
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/rector --dry-run
```

Di Windows PowerShell, gunakan `vendor\bin\pint.bat` dan
`vendor\bin\phpstan.bat` jika file executable Unix tidak tersedia.

---

## Tata Letak Proyek

| Path | Tujuan |
|---|---|
| `app/Filament/` | Halaman, resource, widget admin/pengguna/welcome |
| `app/Livewire/` | Komponen interaktif |
| `app/Models/` | Model Eloquent |
| `app/Services/` | Layanan domain dan integrasi |
| `app/Support/` | Helper bersama (AppPlatform, Platform/*, MobileNav, Phone) |
| `app/Enums/` | Enum domain dan platform |
| `app/Console/Commands/ServePlatformCommand/` | `serve:web|mobile|desktop` |
| `app/Capacitor/UserApp/` | Shell Capacitor user (android, ios, electron) |
| `app/Capacitor/AdminApp/` | Shell Capacitor admin |
| `config/` | Konfigurasi aplikasi, termasuk `app-platform.php` |
| `database/` | Migrasi, factory, seeder |
| `docs/` | Dokumentasi platform, perintah, deployment |
| `lang/` | Terjemahan JSON/paket Laravel |
| `public/` | Titik masuk publik dan aset yang sudah dibangun |
| `public/build/{web,mobile,desktop}/` | Output Vite per platform |
| `resources/views/{Welcome,User,Admin,Shared}/` | Blade per panel + komponen bersama |
| `resources/css/Shared/Shared.css` | Resep visual bersama (kaca topbar/sidebar, carousel) |
| `routes/` | Rute web dan API (tidak ada lagi routes/mobile, routes/desktop) |
| `tests/` | Pengujian Pest, dipisah `Unit` / `Integration` / `Feature` |

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

Workflow CI sudah tersedia di `.github/workflows/`: `ci.yml`,
`build-web.yml`, `build-mobile.yml`, `build-desktop.yml`, `deploy-vps.yml`,
`production-deploy.yml`, `summary.yml`.

---

## Dokumentasi

| Dokumen | Deskripsi |
|---|---|
| [docs/platform-testing/platform-testing.md](docs/platform-testing/platform-testing.md) | Cara menguji 8 target runtime, paksa platform via header |
| [docs/command-guide.md](docs/command-guide/command-guide.md) | Perintah, prasyarat, pemecahan masalah |
| [docs/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md) | Pilih mode web, mobile, atau desktop |
| [docs/environment-configuration.md](docs/environment-configuration/environment-configuration.md) | Pelapisan environment dan file `.env.*` |
| [docs/asset-compilation.md](docs/asset-compilation/asset-compilation.md) | Proses build Vite dan pengaturan HMR |
| [docs/platform-support.md](docs/platform-support/platform-support.md) | Arsitektur dukungan platform, komponen, dan alur data |
| [docs/platform-features.md](docs/platform-features/platform-features.md) | Matriks fitur per platform |
| [docs/deployment/web/web.md](docs/deployment/web/web.md) | Deployment web |
| [docs/deployment/mobile/mobile.md](docs/deployment/mobile/mobile.md) | Build APK/AAB/IPA |
| [docs/deployment/desktop/desktop.md](docs/deployment/desktop/desktop.md) | Build .exe/.dmg |
| [docs/arsitektur/arsitektur.md](docs/arsitektur/arsitektur.md) | Arsitektur aplikasi |
| [docs/autentikasi/autentikasi.md](docs/autentikasi/autentikasi.md) | Alur autentikasi |
| [docs/welcome-panel/welcome-panel.md](docs/welcome-panel/welcome-panel.md) | Panel Welcome (storefront) |
| [docs/messages-system/messages-system.md](docs/messages-system/messages-system.md) | Sistem pesan |
| [docs/katalog-keranjang/katalog-keranjang.md](docs/katalog-keranjang/katalog-keranjang.md) | Katalog dan keranjang |
| [docs/catalog-actions/catalog-actions.md](docs/catalog-actions/catalog-actions.md) | Aksi katalog |
| [docs/pengujian/panduan-pengujian.md](docs/pengujian/panduan-pengujian.md) | Panduan pengujian |
| [docs/README.md](docs/README.md) | Indeks dokumentasi |

---

## Masalah yang Sering Muncul

**`serve:mobile` dan `serve:desktop` memakai port yang sama.**
Selalu teruskan `--port`. Ketiganya melempar ke `php artisan serve` tanpa
default port sendiri.

**Halaman tanpa CSS/JS setelah ganti mode.**
Aset belum di-build untuk mode itu. Jalankan `npm run build:mobile` (atau
`:web`/`:desktop`), lalu `php artisan platform:clear`.

**`.env.web` / `.env.mobile` / `.env.desktop` tidak terbaca.**
File itu opsional dan dilewati kalau tidak ada. Salin dari
`.{mode}.example`, lalu `php artisan platform:clear`.

**Error `Your lock file does not contain a compatible set of packages`.**
`google/cloud-firestore` membutuhkan ekstensi `ext-grpc`. Pasang ekstensi itu
di environment build — jangan pakai `--ignore-platform-req`, karena itu
menyembunyikan ketidaksesuaian lock file dengan platform.

**`npm run android` / `npx cap` tidak ditemukan.**
Perintah itu hanya ada di dalam `app/Capacitor/{UserApp,AdminApp}`, bukan di
root repo. Masuk ke foldernya lebih dulu.

**`npx cap run android --watch` gagal.**
Opsi itu tidak ada. Gunakan `-l` atau `--live-reload`.

**Ikon/menu tidak muncul di shell.**
Buka `/health` dari AI Core bila CBIR dipakai, lalu jalankan
`php artisan cbir:check` untuk memastikan backend bisa menjangkau layanannya.

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

Hak Cipta (c) 2026 Anugerah Ahmad Fachrurochim.