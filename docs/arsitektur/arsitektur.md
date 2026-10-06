# Arsitektur Aplikasi

## Tiga Panel Filament

Aplikasi memakai tiga panel Filament yang berbagi basis kode storefront:

| Panel | ID | Path | Sifat |
|---|---|---|---|
| Admin | `admin` | `/admin` | Khusus staf. Guard `web` + middleware `SuperAdmin`. **Tanpa registrasi.** |
| User | `user` | `/user` | Area member. Punya Auth Landing (`/user/auth`), Sign-In, reset kata sandi OTP, verifikasi email. **Tanpa halaman pendaftaran.** |
| Welcome | `welcome` | `/welcome` | Storefront publik, bisa dibuka guest. Tanpa halaman auth sendiri; guest yang tertahan diarahkan ke `/user/auth` (Sign In + Google). |

Ketiga panel memakai mode `spa()` (navigasi Livewire). Pindah **antar panel**
(contoh: `/welcome/...` ke `/user/signin`) harus memakai full reload
(`navigate: false`), karena navigasi SPA hanya andal di dalam satu panel.

## Konvensi Penamaan (Wajib Diikuti)

Hasil refactor penamaan, berlaku untuk ketiga panel:

| Konsep | Class / Folder | Contoh |
|---|---|---|
| Sign-In (login) | `...Auth\SignIn\SignIn` | `App\Filament\User\Auth\SignIn\SignIn` |
| Auth landing (pintu masuk tamu) | `...Auth\Auth\Auth` | `App\Filament\User\Auth\Auth\Auth` |
| Home (dashboard) | `...Pages\Home\Home` | `App\Filament\Welcome\Pages\Home\Home` |

Catatan: `...Auth\SignUp\SignUp` masih ada di repo, tapi **tidak terdaftar**
sebagai halaman — `->registration()` di `UserPanelProvider` dikomentari, jadi
route `filament.user.auth.register` tidak ada. Pendaftaran hanya lewat tombol
Google di `/user/auth`.

**Satu folder per file**: setiap file PHP dan setiap file Blade tinggal di dalam
folder yang namanya sama dengan nama file (tanpa ekstensi). Contoh:

- `app/Filament/User/Auth/SignIn/SignIn.php` (namespace `...Auth\SignIn`)
- `resources/views/User/social-buttons/auth-buttons/auth-buttons.blade.php`
  dipakai sebagai `User.social-buttons.auth-buttons.auth-buttons`

**Yang tidak ikut di-rename** (disengaja, karena ini kontrak eksternal):

- Nama route Filament: `filament.user.auth.login`, `filament.user.auth.index`,
  `filament.*.pages.home`.
- URL: `/user/auth`, `/user/signin`, `/admin/signin`, `/welcome/home`, URI API.
  Slug auth diatur lewat `loginRouteSlug('signin')` di masing-masing panel
  provider; URL lama (`/user/login`, `/user/signup`, `/user/register`,
  `/admin/login`) dilayani redirect di `routes/web/web.php` supaya bookmark lama
  tidak jadi 404 — `/user/signup` dan `/user/register` ke `/user/auth`. Kode
  sebaiknya memakai `route('filament.<panel>.auth.login')`, bukan URL literal.
- Base class Filament: `Filament\Pages\Auth\Login`,
  `Filament\Pages\Dashboard`.

**"Register" hanya boleh muncul sebagai kontrak vendor.** Penamaan kita sendiri
selalu Sign Up — class, view, nilai `$authMode`, dan label yang dilihat user:

| Milik kita (pakai "Sign Up") | Kontrak vendor (tetap "Register") |
|---|---|
| `...Auth\SignUp\SignUp` | `Filament\Pages\Auth\Register` (base class) |
| `User/auth/sign-up/sign-up` | `filament.*.auth.register` (nama route) |
| `$authMode === 'signup'` | `->registerAction()`, `wire:submit="register"` (API Filament) |
| label `__('Sign Up')` | segment URL `/register` + `/signup` |
- Label yang dilihat user (diatur lewat `getHeading()`, label field, dan file bahasa).

## Middleware Penting

| Middleware | Fungsi |
|---|---|
| `AuthenticateWelcome` | Gerbang panel welcome. Path publik (`welcome`, `welcome/home`, `welcome/products/*`, `welcome/packages/*`, `welcome/messages/*`, ...) boleh dibuka guest; sisanya dilempar ke halaman auth pertama panel user (`/user/auth`, Sign In + Google). Konstanta `LOGIN_ROUTE = 'filament.user.auth.index'`. |
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
