# Indeks Dokumentasi

Dokumen teknis aplikasi Dekorasi Bunga Pernikahan / Wedding Flower Decorations (Mobile & Backend).
Seluruh dokumen memakai Bahasa Indonesia.

## Daftar Lengkap Dokumen

Berikut adalah daftar semua dokumen teknis yang tersedia, terorganisir berdasarkan topik:

| No | Dokumen | Path | Topik Utama |
|---|---|---|---|
| 1 | [Arsitektur Aplikasi](arsitektur/arsitektur.md) | arsitektur/arsitektur.md | Struktur panel (welcome/admin), middleware, konvensi penamaan, identitas guest, session url.intended |
| 2 | [Autentikasi](autentikasi/autentikasi.md) | autentikasi/autentikasi.md | Alur Sign-In, Sign-Up, OTP, login sosial, guard, checkbox persetujuan, auth landing page |
| 3 | [Katalog & Keranjang](katalog-keranjang/katalog-keranjang.md) | katalog-keranjang/katalog-keranjang.md | Aksi katalog, anti-419 guest flow, cart, checkout, wishlist, ulasan, quantity behavior |
| 4 | [Panduan Pengujian](pengujian/panduan-pengujian.md) | pengujian/panduan-pengujian.md | Cara menjalankan test Pest, struktur file test, pola umum, batasan yang diketahui |
| 5 | [Panel Welcome](welcome-panel/welcome-panel.md) | welcome-panel/welcome-panel.md | Storefront publik, halaman, resources, widgets, guest identity, chat system, CBIR search |
| 6 | [Aksi Katalog](catalog-actions/catalog-actions.md) | catalog-actions/catalog-actions.md | Matriks aksi detail, chat_admin, wishlist_detail, getChatUser(), redirectGuestToLogin() |
| 7 | [Sistem Pesan](messages-system/messages-system.md) | messages-system/messages-system.md | Database schema (fm_inboxes, fm_messages), Livewire components, unread badge, lampiran media |
| 8 | [Pohon Keputusan Perintah](command-decision-tree/command-decision-tree.md) | command-decision-tree/command-decision-tree.md | Flowchart pemilihan perintah php artisan serve:web/mobile/desktop, pemecahan masalah |
| 9 | [Panduan Perintah](command-guide/command-guide.md) | command-guide/command-guide.md | Referensi perintah lengkap serve:web/mobile/desktop, build commands, troubleshooting |
| 10 | [Matriks Fitur Platform](platform-features/platform-features.md) | platform-features/platform-features.md | 7 fitur (camera, webrtc, file_system, desktop_notifications, push_notifications, auto_updates, app_badge) per platform |
| 11 | [Arsitektur Dukungan Platform](platform-support/platform-support.md) | platform-support/platform-support.md | Mode platform, enum RuntimePlatform (8 kasus), bootstrap alar, helper global, kamera CBIR, notifikasi |
| 12 | [Strategi Konfigurasi Lingkungan](environment-configuration/environment-configuration.md) | environment-configuration/environment-configuration.md | Struktur file .env.*, prioritas penggabungan, SESSION_DRIVER per platform, VITE_PLATFORM, variabel khusus Capacitor |
| 13 | [Kompilasi Aset](asset-compilation/asset-compilation.md) | asset-compilation/asset-compilation.md | Pipeline build Vite multi-platform, entry point per platform, HMR per platform, konstanta __VITE_PLATFORM__ |
| 14 | [Deployment Web](deployment/web/web.md) | deployment/web/web.md | Deployment web, konfigurasi produksi |
| 15 | [Deployment Mobile](deployment/mobile/mobile.md) | deployment/mobile/mobile.md | Build APK/AAB/IPA, shell mobile Capacitor |
| 16 | [Deployment Desktop](deployment/desktop/desktop.md) | deployment/desktop/desktop.md | Build .exe/.dmg, shell desktop Capacitor Electron |
| 17 | [Pohon Keputusan Penggunaan Perintah (Capacitor)](command-guide/command-guide.md#pohon-keputusan-cepat) | command-guide/command-guide.md | Quick decision tree for command selection |
| 18 | [Sistem Penentuan Fitur](platform-features/platform-features.md#ketersediaan-fitur-berdasarkan-kelompok-platform) | platform-features/platform-features.md | Fitur per kelompok platform (Website, MobileApp, DesktopApp) |
| 19 | [Welcome Panel (Indeks)](welcome-panel/welcome-panel.md) | welcome-panel/welcome-panel.md | Indeks lengkap welcome panel (register, auth, navigation, pages, resources, widgets, guest identity, chat, CBIR) |
| 20 | [Panduan Pengujian (Lama)](pengujian/panduan-pengujian.md) | pengujian/panduan-pengujian.md | Batasan yang diketahui, cakupan test per fitur baru |
| 21 | [Security Best Practices](security-best-practices.md) | security-best-practices.md | Praktik keamanan terbaik: environment, auth, data protection, API security, deployment checklist |
| 22 | [Tipografi & Gaya Teks](tipografi/tipografi.md) | tipografi/tipografi.md | Dua lapisan tipografi form admin (per-peran AppTypography + per-field TextStyles), penyimpanan fm_app_settings, injeksi CSS trio panel, pemakai blade |

---

## Konvensi Penting (hasil refactor terakhir)

- **Sign-In** = halaman login (`...Auth\SignIn\SignIn`), **Sign-Up** = halaman registrasi
  (`...Auth\SignUp\SignUp`), **Home** = halaman dashboard (`...Pages\Home\Home`).
  Berlaku untuk kedua panel saat ini: `welcome` (path `/`) dan `admin` (path `/admin`).
  **Nama route tidak berubah**:
  `filament.welcome.auth.login` / `filament.welcome.auth.register` /
  `filament.admin.auth.login`. URL auth panel mengikuti penamaan class:
  `/signin`, `/signup`, `/admin/signin` (slug `loginRouteSlug` /
  `registrationRouteSlug` di panel provider). URL lama `/user/login`,
  `/user/register`, `/admin/login` dilayani redirect supaya bookmark lama
  tidak jadi 404.
- **Satu folder per file**: setiap file Blade dan setiap class PHP tinggal di folder
  yang namanya sama dengan nama file-nya. Contoh:
  `resources/views/User/social-buttons/auth-buttons/auth-buttons.blade.php`
  dipakai sebagai `User.social-buttons.auth-buttons.auth-buttons`.
- Panel **admin tidak punya registrasi**: tidak ada class
  `App\Filament\Admin\Auth\Register`, tidak ada route `/admin/register`.
- **Panel user sudah direfleksi ke welcome**: prefix nama route berubah dari
  `filament.user.*` ke `filament.welcome.*`, bookmark lama dilayani redirect:
  `/user/*` -> root, `/login` -> `/signin`, `/register` -> `/signup`.

## Catatan Arsitektur Terbaru (2026-10-21)

> Panel `user` sudah **dilebur** ke panel `welcome` di root, jadi:
> - hanya ada dua panel: `welcome` (path `/`) dan `admin` (path `/admin`)
> - prefix nama route berubah dari `filament.user.*` ke `filament.welcome.*`
> - bookmark lama dilayani redirect: `/user/*` -> root, `/login` -> `/signin`,
>   `/register` -> `/signup`
> - bottom nav HP dan chrome ikut memakai panel `welcome`
> - ringkasan terbaru ada di `AGENTS.md` -> bagian "SATU PANEL: 'user' DILEBUR KE 'welcome'"

URL canonical berada di root; URL `/user/*` dan `/welcome/*` hanya redirect legacy.

---

## Sumber kebenaran platform

- Panel storefront + akun: `welcome` di root, home `/home`.
- Panel admin: `admin` di `/admin`.
- Shell user: `app/Capacitor/DekstopApp`.
- Shell admin: `app/Capacitor/DekstopAdminApp`.
- URL `/user/*` dan `/welcome/*` adalah legacy redirect, bukan path panel aktif.
- `serve:web`, `serve:mobile`, dan `serve:desktop` hanya menerima `--host`,
  `--port`, dan `--tries`, lalu mendelegasikan ke Laravel `serve`.

## Struktur File Environment

| File | Kegunaan | Wajib/Opsional |
|---|---|---|
| `.env` | Konfigurasi dasar bersama — kredensial database, mail, Firebase, Midtrans, dll. | **Wajib** |
| `.env.web` | Override khusus web — APP_URL, SESSION_DRIVER=cookie, VITE_PLATFORM=web | Opsional |
| `.env.mobile` | Override Capacitor Mobile — APP_URL=10.0.2.2:8001, SESSION_DRIVER=database, VITE_PLATFORM=mobile | Opsional |
| `.env.desktop` | Override Capacitor Desktop — APP_URL=localhost:8002, SESSION_DRIVER=file, VITE_PLATFORM=desktop | Opsional |
| `.env.example` | Template untuk `.env` | — |
| `.env.web.example` | Template untuk `.env.web` | — |
| `.env.mobile.example` | Template untuk `.env.mobile` | — |
| `.env.desktop.example` | Template untuk `.env.desktop` | — |

Prioritas pembacaan: file platform lebih diutamakan daripada `.env`. Jika file
platform tidak ada, aplikasi melanjutkan diam-diam dengan `.env` saja.

---

## Platform yang Didukung

| Platform | Mode Perintah | Port | Fitur Utama |
|---|---|---|---|
| **Web** | `php artisan serve:web` | 8000 | Browser, WebRTC kamera, session cookie |
| **Mobile (Capacitor)** | `php artisan serve:mobile` | 8001 | Shell WebView, FCM push, input file kamera, database session |
| **Desktop (Capacitor Electron)** | `php artisan serve:desktop` | 8002 | Shell Electron, Web Notifications, file session; auto-update masih capability flag |

> **Catatan arsitektur:** Shell Capacitor hanyalah *WebView loader*. Tidak ada
> PHP tertanem, tidak ada bridge native, dan tidak ada database proxy. Semua
> logika bisnis berada di server Laravel; shell hanya menampilkan UI.

---

## Daftar Isi Lengkap

[Arsitektur](arsitektur/arsitektur.md) |
[Autentikasi](autentikasi/autentikasi.md) |
[Katalog & Keranjang](katalog-keranjang/katalog-keranjang.md) |
[Panduan Pengujian](pengujian/panduan-pengujian.md) |
[Panel Welcome](welcome-panel/welcome-panel.md) |
[Aksi Katalog](catalog-actions/catalog-actions.md) |
[Sistem Pesan](messages-system/messages-system.md) |
[Pohon Keputusan Perintah](command-decision-tree/command-decision-tree.md) |
[Panduan Perintah](command-guide/command-guide.md) |
[Matriks Fitur Platform](platform-features/platform-features.md) |
[Arsitektur Dukungan Platform](platform-support/platform-support.md) |
[Strategi Konfigurasi Lingkungan](environment-configuration/environment-configuration.md) |
[Kompilasi Aset](asset-compilation/asset-compilation.md) |
[Deployment Web](deployment/web/web.md) |
[Deployment Mobile](deployment/mobile/mobile.md) |
[Deployment Desktop](deployment/desktop/desktop.md) |
[Security Best Practices](security-best-practices.md)

---

## Cepat Memulai (Quick Start)

```bash
# Clone repositori
git clone https://github.com/aanugerahahmadf/WebApp.git
cd WebApp

# Instal dependensi
composer install
npm install

# Buat file environment
cp .env.example .env
php artisan key:generate

# Buat database MySQL, atur kredensial di .env, lalu migrasi
php artisan migrate --seed
npm run build:web
php artisan serve:web --port=8000

# Buat akun admin pertama
php artisan app:init-admin admin@example.com "PasswordKuat123" "Admin"

# Buka http://127.0.0.1:8000
```

> **Penting:** Selalu teruskan `--port` ke `serve:web`/`serve:mobile`/`serve:desktop`.
> Tanpa `--port`, ketiga mode akan berebut port yang sama melewati `php artisan serve`.

---
