# Arsitektur Aplikasi

## Tiga Panel Filament

Aplikasi memakai tiga panel Filament yang berbagi basis kode storefront:

| Panel | ID | Path | Sifat |
|---|---|---|---|
| Admin | `admin` | `/admin` | Khusus staf. Guard `web` + middleware `SuperAdmin`. **Tanpa registrasi.** |
| User | `user` | `/user` | Area member. Punya Sign-In, Sign-Up, reset kata sandi OTP, verifikasi email. |
| Welcome | `welcome` | `/welcome` | Storefront publik, bisa dibuka guest. Tanpa halaman auth sendiri; guest diarahkan ke login panel user. |

Ketiga panel memakai mode `spa()` (navigasi Livewire). Pindah **antar panel**
(contoh: `/welcome/...` ke `/user/signin`) harus memakai full reload
(`navigate: false`), karena navigasi SPA hanya andal di dalam satu panel.

## Konvensi Penamaan (Wajib Diikuti)

Hasil refactor penamaan, berlaku untuk ketiga panel:

| Konsep | Class / Folder | Contoh |
|---|---|---|
| Sign-In (login) | `...Auth\SignIn\SignIn` | `App\Filament\User\Auth\SignIn\SignIn` |
| Sign-Up (registrasi) | `...Auth\SignUp\SignUp` | `App\Filament\User\Auth\SignUp\SignUp` |
| Home (dashboard) | `...Pages\Home\Home` | `App\Filament\Welcome\Pages\Home\Home` |

**Satu folder per file**: setiap file PHP dan setiap file Blade tinggal di dalam
folder yang namanya sama dengan nama file (tanpa ekstensi). Contoh:

- `app/Filament/User/Auth/SignIn/SignIn.php` (namespace `...Auth\SignIn`)
- `resources/views/User/social-buttons/auth-buttons/auth-buttons.blade.php`
  dipakai sebagai `User.social-buttons.auth-buttons.auth-buttons`

**Yang tidak ikut di-rename** (disengaja, karena ini kontrak eksternal):

- Nama route Filament: `filament.user.auth.login`, `filament.user.auth.register`,
  `filament.*.pages.home`.
- URL: `/user/signin`, `/user/signup`, `/admin/signin`, `/welcome/home`, URI API.
  Slug auth diatur lewat `loginRouteSlug('signin')` /
  `registrationRouteSlug('signup')` di masing-masing panel provider; URL lama
  (`/user/login`, `/user/register`, `/admin/login`) dilayani redirect di
  `routes/web/web.php` supaya bookmark lama tidak jadi 404. Kode sebaiknya
  memakai `route('filament.<panel>.auth.login')`, bukan URL literal.
- Base class Filament: `Filament\Pages\Auth\Login`,
  `Filament\Pages\Auth\Register`, `Filament\Pages\Dashboard`.
- Label yang dilihat user (diatur lewat `getHeading()`, label field, dan file bahasa).

## Middleware Penting

| Middleware | Fungsi |
|---|---|
| `AuthenticateWelcome` | Gerbang panel welcome. Path publik (`welcome`, `welcome/home`, `welcome/products/*`, `welcome/packages/*`, `welcome/messages/*`, ...) boleh dibuka guest; sisanya dilempar ke login panel user. Konstanta `LOGIN_ROUTE = 'filament.user.auth.login'`. |
| `VerifyCsrfToken` (kustom) | Pengecualian CSRF untuk `admin/*`, `livewire/*`, dan webhook pembayaran. |
| `SetLocale` | Sinkronisasi bahasa (grup `web` dan `api`). |
| `ClerkFilamentAuth` | Login otomatis via token Sanctum (`clerk_token`). |
| `EnsureProfileComplete` | Melempar user yang profilnya belum lengkap; target lemparnya login user. |
| `AuthenticateSession`, `SuperAdmin` | Keamanan sesi dan batas akses panel admin. |

`bootstrap/app.php` juga mengatur `redirectGuestsTo()` global ke login user.

## Identitas Guest (`GuestIdentity`)

Guest yang membuka storefront **tidak di-login-kan** (supaya tidak mendapat
akses cart/order milik orang lain). Sebagai gantinya browser guest membawa
cookie `welcome_guest_id` yang dipetakan ke baris user `@guest.local`.
Dipakai oleh fitur chat agar guest bisa mengobrol dengan admin. Didaftarkan
sebagai singleton di `AppServiceProvider`.

## Session `url.intended`

Saat guest melakukan aksi yang butuh login (Add to Cart, wishlist, lapor,
ulasan), URL halaman asal disimpan di `session('url.intended')` sebelum
diarahkan ke login. Dipakai oleh:

- Tombol kembali di halaman Sign-In (`SignIn::getBackUrl()`), dan
- Kontrak `LoginResponse` (apabila tidak ada panel aktif).
