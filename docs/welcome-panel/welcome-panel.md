# Welcome panel: storefront dan akun

Dokumen ini menjelaskan panel Filament `welcome` yang menjadi storefront publik
sekaligus area akun pelanggan. Panel lama `user` sudah tidak didaftarkan sebagai
panel Filament terpisah.

## 1. Sumber kebenaran

- Provider: `app/Providers/Filament/WelcomePanelProvider/WelcomePanelProvider.php`
- Panel ID: `welcome`
- Path panel: `/` (root)
- Guard: `web`
- Home: `/home`
- Sign-in: `/signin`
- Sign-up: `/signup`
- Auth landing: `/auth`
- Admin panel: panel terpisah dengan path `/admin` dan guard `admin`

Route lama `/welcome/*`, `/user/*`, `/login`, dan `/register` ditangani oleh
redirect kompatibilitas di `routes/web/web.php`. Gunakan URL root yang tercantum
di dokumen ini untuk link baru; jangan membuat link baru ke URL legacy.

## 2. Area publik dan area yang memerlukan akun

`AuthenticateWelcome` tidak menolak semua guest seperti middleware Filament
standar. Halaman katalog dan konten publik dapat dibuka tanpa login. Halaman
akun dan operasi yang mengubah data mengarahkan guest ke `/auth` serta menyimpan
`url.intended`, sehingga pengguna kembali ke halaman awal setelah berhasil login.

### Halaman publik utama

| URL | Keterangan |
|---|---|
| `/home` | Beranda/storefront |
| `/flowerdecorationscatalog` | Daftar katalog |
| `/flowerdecorationscatalog/{record}` | Detail katalog |
| `/flowerdecorationscatalog/{record}/checkout` | Checkout; guard tambahan di komponen |
| `/cbir-search` | Pencarian katalog berbasis gambar |
| `/help-center` | Pusat bantuan |
| `/privacy-policy` | Kebijakan privasi |
| `/privacy-terms` | Privacy terms |
| `/terms-of-service` | Ketentuan layanan |
| `/wedding-policy` | Kebijakan dekorasi |
| `/messages/{id?}` | Pesan; guest dapat membuat percakapan admin |

### Halaman akun

| URL | Keterangan |
|---|---|
| `/carts` | Keranjang |
| `/orders` dan `/orders/{record}` | Pesanan |
| `/wishlists` | Favorit |
| `/reviews` | Ulasan pengguna |
| `/vouchers` | Voucher |
| `/histories` | Riwayat |
| `/settings` | Pengaturan akun |
| `/settings/password-security` | Password dan keamanan |
| `/edit-profile-page` | Profil |
| `/complete-profile` | Melengkapi profil |
| `/notifications/{id}` | Detail notifikasi |

Nama route Filament dapat dilihat dengan `php artisan route:list`; gunakan
`::getUrl()` dari class resource/page di kode PHP, bukan meng-hardcode slug.

## 3. Authentication

Provider mendaftarkan halaman auth berikut ke panel `welcome`:

- `SignIn` pada `/signin`;
- `SignUp` pada `/signup`;
- `UserAuth` pada `/auth` sebagai pilihan sign-in/social auth;
- OTP verification, reset password berbasis OTP, dan email verification;
- `CompleteProfilePage`, `SecurityQuestion`, `TwoFactorAuth`, dan
  `DeviceTrustPrompt` sebagai halaman akun.

Panel admin **tidak** memiliki registrasi publik. Admin dibuat melalui seeder,
`app:init-admin`, atau alur administrasi yang telah diizinkan.

## 4. GuestIdentity dan chat admin

`app/Services/GuestIdentity/GuestIdentity.php` menyediakan identitas sementara
berbasis session untuk guest yang membuka chat admin. Identitas ini bukan akun
login:

1. service membuat/mengambil user sintetis dengan email domain `@guest.local`;
2. ID disimpan pada session;
3. `ChatService` membuat inbox antara guest dan admin;
4. pesan konteks katalog dapat dikirim sebelum guest login;
5. daftar akun, badge, dan statistik mengecualikan baris guest.

`MessagesPage` tetap memeriksa bahwa user/guest adalah anggota inbox sebelum
memuat percakapan. ID inbox pada URL tidak boleh dianggap sebagai izin akses.

## 5. Katalog dan aksi detail

Resource katalog storefront berada di namespace `App\Filament\Welcome\Resources`.
Aksi detail yang umum:

| Aksi | Guest | User login |
|---|---|---|
| Chat Admin | Boleh melalui `GuestIdentity` | Membuka/melanjutkan inbox user |
| Tambah keranjang | Redirect `/auth` | Menambah cart |
| Checkout | Redirect/guard | Membuka wizard checkout |
| Wishlist | Redirect `/auth` | Toggle wishlist langsung |
| Ulasan | Redirect `/auth` | Membuka form ulasan |
| Bagikan | Share dialog | Share dialog |
| Laporkan | Redirect/flow chat | Mengirim laporan ke admin |

Aksi Livewire/Filament yang membutuhkan navigasi memakai `$livewire->redirect()`
ketika dipanggil dari infolist action. Aksi direct seperti wishlist tidak boleh
diberi `->form()` kosong karena itu mengubahnya menjadi modal POST dan dapat
menyebabkan kegagalan CSRF/session.

## 6. Pesan dan lampiran

Halaman pesan menggunakan komponen Livewire di `app/Livewire/Welcome/Messages`.
Operasi domain dipusatkan di `ChatService`, termasuk inbox, pesan konteks
katalog, laporan, reply, reaction, star, typing indicator, dan forwarding.

Validasi keanggotaan inbox harus dilakukan sebelum:

- membaca thread;
- mengunduh lampiran;
- menghapus pesan;
- meneruskan pesan ke inbox lain.

Konfigurasi broadcast, queue, media disk, dan ukuran upload berasal dari `.env`
serta config Laravel; shell Capacitor hanya memuat halaman server.

## 7. CBIR

Alur CBIR adalah:

1. `CbirSearchPage` menerima file gambar;
2. mode web memakai WebRTC/input browser, sedangkan bundle mobile/desktop
   memakai input file `capture` pada shell;
3. `CBIRService` mengirim gambar ke `CBIR_API_URL`;
4. ID hasil disimpan pada session;
5. grid katalog memfilter produk/paket berdasarkan ID tersebut.

Periksa layanan dengan `php artisan cbir:check` (opsional `--json` atau `--url=`).
Sinkronisasi dataset memakai `php artisan cbir:sync` atau `php artisan ai:sync`.
Keduanya dapat memerlukan folder `../ai_core/data` dan service AI Core yang
aktif; lihat opsi `--no-rebuild`, `--skip-index`, atau `--rebuild-only` sebelum
menjalankan sinkronisasi pada produksi.

## 8. Konvensi pengembangan

- Jangan menambahkan provider panel `user` baru.
- Gunakan `filament.welcome.*` untuk nama route panel storefront.
- Gunakan `route()` atau `Page::getUrl()` untuk URL internal.
- Perlakukan `/user/*` dan `/welcome/*` sebagai URL legacy saja.
- Uji guest, user login, dan desktop/mobile shell secara terpisah.
- Setelah mengubah provider atau route, jalankan `php artisan route:list` dan
  test feature panel yang relevan.

## Referensi

- [Arsitektur](../arsitektur/arsitektur.md)
- [Autentikasi](../autentikasi/autentikasi.md)
- [Aksi katalog](../catalog-actions/catalog-actions.md)
- [Sistem pesan](../messages-system/messages-system.md)
- [Pengujian](../pengujian/panduan-pengujian.md)
