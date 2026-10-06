# Strategi Konfigurasi Environment (Capacitor)

Dokumen ini menjelaskan bagaimana aplikasi mengelola variabel environment di tiga
mode platform: **web**, **mobile**, dan **desktop**. Memahami strategi ini sangat
penting untuk mengonfigurasi aplikasi dengan benar selama pengembangan maupun
produksi.

> **Catatan:** Semua variabel `NATIVEPHP_*` dan `NATIVE_*` telah dihapus.
> Konfigurasi shell Capacitor (app id, versi, URL) berada di `capacitor.config.json`
> dan `electron-builder.config.js` shell masing-masing, bukan di server Laravel.

---

## Ikhtisar

Aplikasi menggunakan file `.env` dasar yang dilengkapi dengan file override
**opsional** per platform. Ketika file platform-spesifik ada, nilainya akan
menggantikan kunci yang sama di `.env`. Jika file platform-spesifik tidak ada,
aplikasi tetap berjalan dengan baik hanya menggunakan `.env`.

```
.env                  ← selalu dimuat (wajib)
.env.web              ← dimuat di atas .env saat berjalan dalam mode Web   (opsional)
.env.mobile           ← dimuat di atas .env saat berjalan dalam mode Mobile (opsional)
.env.desktop          ← dimuat di atas .env saat berjalan dalam mode Desktop (opsional)
```

`EnvironmentManager` menangani penggabungan ini selama bootstrap aplikasi,
sebelum route didaftarkan atau service di-resolve.

---

## Struktur File dan Tujuan

### `.env` — Konfigurasi Dasar (Wajib)

File `.env` dasar berisi pengaturan yang digunakan bersama di semua platform:
kredensial database, konfigurasi mail, API key pihak ketiga, serta nilai
default untuk driver session, cache, dan queue.

```dotenv
APP_NAME="Wedding Flower Decorations"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=file
QUEUE_CONNECTION=sync

VITE_APP_NAME="${APP_NAME}"

# ... database, mail, Firebase, Midtrans, dll.
```

Ketiga mode platform mewarisi setiap variabel dari `.env`. File
platform-spesifik hanya diperlukan ketika Anda ingin mengubah nilai untuk mode
tertentu.

---

### `.env.web` — Override Platform Web (Opsional)

Dimuat ketika aplikasi dijalankan dengan `php artisan serve:web`. Biasanya
digunakan untuk mengatur `APP_URL` yang benar dan driver session yang ramah
browser.

Salin contoh untuk memulai:

```bash
cp .env.web.example .env.web
```

Isi tipikal:

```dotenv
APP_URL=http://localhost:8000

SESSION_DRIVER=cookie
SESSION_LIFETIME=120

CACHE_DRIVER=file
QUEUE_CONNECTION=sync

FILESYSTEM_DISK=public

VITE_PLATFORM=web

SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000
```

---

### `.env.mobile` — Override Platform Mobile (Opsional)

Dimuat ketika aplikasi dijalankan dengan `php artisan serve:mobile`.
Mengonfigurasi backend untuk shell Capacitor mobile (Android/iOS), termasuk
alamat host yang benar untuk emulator dan driver session berbasis database
yang bertahan saat aplikasi di-restart.

Salin contoh untuk memulai:

```bash
cp .env.mobile.example .env.mobile
```

Isi tipikal:

```dotenv
# URL server yang diload shell Capacitor mobile
# 10.0.2.2 adalah alias emulator Android untuk mesin host.
# Untuk perangkat fisik, gunakan IP LAN Anda: http://192.168.1.x:8001
APP_URL=http://10.0.2.2:8001

SESSION_DRIVER=database
SESSION_LIFETIME=10080     # 7 hari — mengurangi frekuensi login ulang di mobile

CACHE_DRIVER=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

VITE_PLATFORM=mobile

# Cermin dari shell, untuk helper sisi PHP (tidak dipakai shell langsung)
CAPACITOR_USER_URL=http://10.0.2.2:8001
CAPACITOR_USER_APP_ID=id.dekorasi.pengantin.user
```

---

### `.env.desktop` — Override Platform Desktop (Opsional)

Dimuat ketika aplikasi dijalankan dengan `php artisan serve:desktop`.
Mengonfigurasi backend untuk shell Capacitor Electron (Windows/macOS) dan
menggunakan session berbasis file, yang sesuai untuk aplikasi lokal pengguna
tunggal.

Salin contoh untuk memulai:

```bash
cp .env.desktop.example .env.desktop
```

Isi tipikal:

```dotenv
# URL server yang diload shell Capacitor desktop
APP_URL=http://localhost:8002

SESSION_DRIVER=file
SESSION_LIFETIME=43200     # 30 hari — aplikasi desktop jarang memerlukan login ulang

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

VITE_PLATFORM=desktop

# Cermin dari shell, untuk helper sisi PHP
CAPACITOR_USER_URL=http://localhost:8002/user
CAPACITOR_ADMIN_URL=http://localhost:8002/admin
CAPACITOR_USER_APP_ID=id.dekorasi.pengantin.user
CAPACITOR_ADMIN_APP_ID=id.dekorasi.pengantin.admin
```

---

## Prioritas Penggabungan: Platform-Spesifik Menggantikan Dasar

Ketika file platform-spesifik ada, nilainya akan menggantikan kunci yang sama
dari `.env`. Variabel yang hanya ada di `.env` diwarisi apa adanya.

**Contoh:**

`.env` (dasar):
```dotenv
SESSION_DRIVER=database
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
```

`.env.web` (override platform):
```dotenv
SESSION_DRIVER=cookie
APP_URL=http://localhost:8000
```

Environment efektif saat berjalan dalam mode Web:
```dotenv
SESSION_DRIVER=cookie          ← dari .env.web (menggantikan .env)
APP_URL=http://localhost:8000  ← dari .env.web (menggantikan .env)
DB_HOST=127.0.0.1              ← dari .env (tidak ada override, diwarisi)
```

Penggabungan terjadi di level runtime PHP dengan menulis ke `$_ENV`,
`$_SERVER`, dan `putenv()`. Helper `env()` milik Laravel dan pemanggilan
`config()` keduanya mengambil nilai yang telah digabung.

---

## File Platform Bersifat Opsional

Aplikasi bekerja dengan sempurna hanya dengan `.env`. File platform-spesifik
hanya diperlukan ketika sebuah variabel harus berbeda antar mode.

| Skenario | File yang Diperlukan |
|----------|----------------------|
| Hanya menjalankan server web | Hanya `.env` |
| Menjalankan web + mobile secara bersamaan | `.env`, `.env.mobile` |
| Ketiga platform secara bersamaan | `.env`, `.env.web`, `.env.mobile`, `.env.desktop` |
| Berjalan di produksi (satu platform) | Hanya `.env` (atau satu file platform) |

Jika file platform tidak ada, `EnvironmentManager` mencatat pesan debug dan
melanjutkan tanpa error — tidak ada yang rusak.

---

## Perilaku Pemuatan EnvironmentManager

Kelas `EnvironmentManager` (`app/Support/Platform/EnvironmentManager/EnvironmentManager.php`)
bertanggung jawab untuk memuat dan menggabungkan file environment platform.
Kelas ini dipanggil oleh `PlatformModeServiceProvider` selama boot aplikasi,
sebelum route atau view di-resolve.

### Urutan pemuatan

1. `PlatformModeServiceProvider::boot()` mendeteksi `PlatformMode` aktif
   (Web, Mobile, atau Desktop).
2. Memanggil `EnvironmentManager::loadPlatformEnvironment(PlatformMode $mode)`.
3. Metode ini me-resolve path file menggunakan `PlatformMode::environmentFile()`,
   misalnya `.env.mobile`.
4. Jika file tidak ada, ia mencatat pesan `debug` dan kembali lebih awal — tidak
   ada error yang dilempar.
5. Jika file ada, ia mengurai setiap baris `KEY=VALUE`, melewati baris kosong
   dan komentar `#`.
6. Setiap variabel ditulis ke `$_ENV`, `$_SERVER`, dan `putenv()`, menimpa
   nilai dasar untuk kunci tersebut.
7. Setelah penggabungan, ia mencatat pesan `info` dengan nama file, jumlah
   variabel, dan jumlah konflik yang diselesaikan.

### Aturan penguraian

- Baris yang dimulai dengan `#` diperlakukan sebagai komentar dan dilewati.
- Baris kosong dilewati.
- Nilai yang dibungkus dengan tanda kutip `"ganda"` atau `'tunggal'` akan
  dihapus tanda kutipnya.
- `null`, `(null)`, `empty`, dan `(empty)` dinormalisasi menjadi string
  kosong `""`.
- `=` pertama pada sebuah baris memisahkan kunci dari nilai — nilai boleh
  mengandung `=`.

### Output log (environment lokal)

```
[info]  Loaded platform environment  {"file":".env.mobile","mode":"mobile","vars_count":12,"conflicts_resolved":3}
```

Ketika tidak ada file platform yang ditemukan:

```
[debug] Platform environment file not found, using base environment  {"file":".env.mobile","mode":"mobile"}
```

---

## Skenario Konfigurasi Umum

### Skenario 1: `SESSION_DRIVER` berbeda per platform

Driver session biasanya berbeda antar platform:

| Platform | Driver yang Direkomendasikan | Alasan |
|----------|------------------------------|--------|
| Web | `cookie` | HTTP stateless; cookie bekerja secara native di browser |
| Mobile | `database` | Bertahan saat aplikasi native di-restart; bekerja dengan token auth |
| Desktop | `file` | Aplikasi lokal pengguna tunggal; session file cepat dan sederhana |

`.env` dasar:
```dotenv
SESSION_DRIVER=database
```

`.env.web`:
```dotenv
SESSION_DRIVER=cookie
```

`.env.desktop`:
```dotenv
SESSION_DRIVER=file
```

Mode Mobile mewarisi `SESSION_DRIVER=database` dari `.env` — tidak
diperlukan override.

---

### Skenario 2: `APP_URL` berbeda per platform

Setiap platform biasanya berjalan pada port (atau host) yang berbeda untuk
memungkinkan pengembangan secara bersamaan:

`.env` (dasar):
```dotenv
APP_URL=http://127.0.0.1:8000
```

`.env.web`:
```dotenv
APP_URL=http://localhost:8000
```

`.env.mobile`:
```dotenv
# Emulator Android merutekan 10.0.2.2 ke mesin host
APP_URL=http://10.0.2.2:8001

# Untuk perangkat Android fisik di jaringan Wi-Fi yang sama:
# APP_URL=http://192.168.1.42:8001
```

`.env.desktop`:
```dotenv
APP_URL=http://localhost:8002
```

---

### Skenario 3: `VITE_PLATFORM` untuk pemilihan aset frontend

Pipeline build Vite menggunakan `VITE_PLATFORM` untuk memilih entry point
JavaScript dan bundle aset yang benar. Atur di setiap file platform agar
browser/aplikasi native memuat bundle yang tepat.

`.env.web`:
```dotenv
VITE_PLATFORM=web
```

`.env.mobile`:
```dotenv
VITE_PLATFORM=mobile
```

`.env.desktop`:
```dotenv
VITE_PLATFORM=desktop
```

Dalam template Blade atau JavaScript, Anda dapat membaca nilai ini:

```js
// resources/js/app-web/app-web.js (atau app-mobile/app-desktop)
const platform = import.meta.env.VITE_PLATFORM ?? 'web';
```

---

### Skenario 4: Menjalankan ketiga platform secara bersamaan

Jalankan setiap platform di terminal terpisah. Masing-masing membaca portnya
sendiri dari file platform-spesifik:

```bash
# Terminal 1 — Web (port 8000)
php artisan serve:web --port=8000

# Terminal 2 — Mobile (port 8001, diatur di .env.mobile)
php artisan serve:mobile --port=8001

# Terminal 3 — Desktop (port 8002, diatur di .env.desktop)
php artisan serve:desktop --port=8002
```

Konvensi penugasan port:

| Platform | Perintah | Port |
|----------|----------|------|
| Web | `php artisan serve:web` | 8000 |
| Mobile | `php artisan serve:mobile` | 8001 |
| Desktop | `php artisan serve:desktop` | 8002 |

---

### Skenario 5: Menyimpan rahasia bersama hanya di `.env`

Rahasia seperti password database, API key, dan kredensial mail hanya perlu
muncul sekali di `.env`. File platform tidak perlu menduplikasinya — semuanya
diwarisi secara otomatis.

```dotenv
# .env — sumber kebenaran tunggal untuk rahasia
DB_HOST=127.0.0.1
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=root
DB_PASSWORD=secret

MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json
```

File platform hanya menggantikan variabel yang benar-benar berbeda per
platform.

---

## Variabel Khusus Capacitor (Cermin Shell)

Variabel di bawah ini **hanya cermin** dari konfigurasi shell Capacitor. Nilai
harus selalu sama dengan isi `capacitor.config.json` shell masing-masing.
Skrip `npm run set:url` menjaga keselarasan.

| Variabel | Sumber di Shell | Digunakan Server Untuk |
|---|---|---|
| `CAPACITOR_USER_URL` | `capacitor.config.json` → `server.url` (UserApp) | Bangun URL absolut ke panel user |
| `CAPACITOR_ADMIN_URL` | `capacitor.config.json` → `server.url` (AdminApp) | Bangun URL absolut ke panel admin |
| `CAPACITOR_USER_APP_ID` | `capacitor.config.json` → `appId` (UserApp) | Deep link FCM / notifikasi |
| `CAPACITOR_ADMIN_APP_ID` | `capacitor.config.json` → `appId` (AdminApp) | Deep link FCM / notifikasi |

> **Jangan** set variabel ini tanpa juga update `capacitor.config.json` via
> `npm run set:url`. Kedua sisi harus selalu sinkron.

---

## Referensi Cepat

```
.env                   → selalu dimuat; semua konfigurasi bersama dan rahasia
.env.web               → (opsional) override web; salin dari .env.web.example
.env.mobile            → (opsional) override mobile; salin dari .env.mobile.example
.env.desktop           → (opsional) override desktop; salin dari .env.desktop.example

Aturan penggabungan:   file platform menang atas .env untuk kunci yang sama
File platform hilang:  aplikasi melanjutkan dengan .env dasar — tidak ada error
Waktu pemuatan:        selama PlatformModeServiceProvider::boot(), sebelum route
```

---

## Lihat Juga

- `app/Support/Platform/EnvironmentManager/EnvironmentManager.php` — implementasi
- `app/Providers/PlatformModeServiceProvider/PlatformModeServiceProvider.php` — tempat pemuatan dipicu
- `app/Enums/PlatformMode/PlatformMode.php` — metode `environmentFile()` mengembalikan nama file per mode
- `.env.web.example`, `.env.mobile.example`, `.env.desktop.example` — file awal beranotasi
- `docs/platform-support.md` — ikhtisar arsitektur platform
- `docs/deployment/mobile/mobile.md` — build shell mobile
- `docs/deployment/desktop/desktop.md` — build shell desktop