# Indeks Dokumentasi

Dokumentasi teknis aplikasi Wedding Organizer (Mobile & Backend).
Seluruh dokumen memakai Bahasa Indonesia.

## Daftar Dokumen

| Dokumen | Isi |
|---|---|
| [Arsitektur](arsitektur/arsitektur.md) | Panel Filament (admin, user, welcome), middleware, identitas guest, konvensi penamaan |
| [Autentikasi](autentikasi/autentikasi.md) | Alur Sign-In, Sign-Up, OTP, login sosial, guard, dan tombol kembali |
| [Katalog & Keranjang](katalog-keranjang/katalog-keranjang.md) | Aksi katalog, alur guest Add to Cart anti-419, cart, checkout, wishlist, ulasan |
| [Panduan Pengujian](pengujian/panduan-pengujian.md) | Cara menjalankan test, struktur Pest, konvensi penulisan test |
| [Panel Welcome](welcome-panel/welcome-panel.md) | Storefront publik dan akses guest (dokumen lama, Bahasa Inggris) |
| [Aksi Katalog](catalog-actions/catalog-actions.md) | Matriks aksi detail katalog (dokumen lama, Bahasa Inggris) |
| [Sistem Pesan](messages-system/messages-system.md) | Sistem chat admin, inbox, dan guest (dokumen lama, Bahasa Inggris) |

## Konvensi Penting (hasil refactor terakhir)

- **Sign-In** = halaman login (`...Auth\SignIn\SignIn`), **Sign-Up** = halaman registrasi
  (`...Auth\SignUp\SignUp`), **Home** = halaman dashboard (`...Pages\Home\Home`).
  Berlaku untuk ketiga panel. **Nama route tidak berubah**:
  `filament.user.auth.login` / `filament.user.auth.register` /
  `filament.admin.auth.login`. URL auth panel mengikuti penamaan class:
  `/user/signin`, `/user/signup`, `/admin/signin` (slug `loginRouteSlug` /
  `registrationRouteSlug` di panel provider). URL lama `/user/login`,
  `/user/register`, `/admin/login` dilayani redirect supaya bookmark lama
  tidak jadi 404.
- **Satu folder per file**: setiap file Blade dan setiap class PHP tinggal di folder
  yang namanya sama dengan nama file-nya. Contoh:
  `resources/views/User/social-buttons/auth-buttons/auth-buttons.blade.php`
  dipakai sebagai `User.social-buttons.auth-buttons.auth-buttons`.
- Panel **admin tidak punya registrasi**: tidak ada class
  `App\Filament\Admin\Auth\Register`, tidak ada route `/admin/register`.
