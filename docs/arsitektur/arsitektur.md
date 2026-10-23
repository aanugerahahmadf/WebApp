# Arsitektur Aplikasi

> **CATATAN ARSITEKTUR (2026-10-21).** Halaman ini ditulis saat masih ada DUA
> panel terpisah: `user` di `/user` dan `welcome` di `/welcome`. Sekarang
> panel `user` sudah **dilebur** ke panel `welcome` di root, jadi:
>
> - hanya ada dua panel: `welcome` (path `/`) dan `admin` (path `/admin`)
> - prefix nama route berubah dari `filament.user.*` ke `filament.welcome.*`
> - bookmark lama dilayani redirect: `/user/*` -> root, `/login` -> `/signin`,
>   `/register` -> `/signup`
> - bottom nav HP dan chrome ikut memakai panel `welcome`
>
> Ringkasan terbaru ada di `AGENTS.md` -> bagian "SATU PANEL: 'user' DILEBUR KE
> 'welcome'". Nama route dan URL yang disebut di bawah sudah disesuaikan;
> bagian yang men_**describe** dua panel terpisah masih perlu dibaca sebagai
> sejarah.

## Dua Panel Filament

Aplikasi memakai dua panel Filament yang berbagi basis kode storefront:

| Panel | ID | Path | Sifat |
|---|---|---|---|
| Admin | `admin` | `/admin` | Khusus staf. Guard `web` + middleware `SuperAdmin`. **Tanpa registrasi.** |
| Welcome | `welcome` | `/` | Storefront publik + area member sekaligus: bisa dibuka guest, dan memuat auth landing (`/auth`), Sign-In, pendaftaran, reset kata sandi OTP, verifikasi email, serta seluruh halaman akun. |

Kedua panel memakai mode `spa()` (navigasi Livewire). Pindah **antar panel**
(contoh: `/...` ke `/admin/...`) harus memakai full reload
(`navigate: false`), karena navigasi SPA hanya andal di dalam satu panel.

## Konvensi Penamaan (Wajib Diikuti)

Hasil refactor penamaan, berlaku untuk kedua panel:

| Konsep | Class / Folder | Contoh |
|---|---|---|
| Sign-In (login) | `...Auth\SignIn\SignIn` | `App\Filament\User\Auth\SignIn\SignIn` |
| Auth landing (pintu masuk tamu) | `...Auth\Auth\Auth` | `App\Filament\User\Auth\Auth\Auth` |
| Home (dashboard) | `...Pages\Home\Home` | `App\Filament\Welcome\Pages\Home\Home` |

Catatan: `...Auth\SignUp\SignUp` **terdaftar** sebagai halaman —
`->registration(SignUp::class)` + `->registrationRouteSlug('signup')` di
`WelcomePanelProvider`, jadi route `filament.welcome.auth.register` ada dan
halamannya hidup di `/signup`.

**Satu folder per file**: setiap file PHP dan setiap file Blade tinggal di dalam
folder yang namanya sama dengan nama file (tanpa ekstensi). Contoh:

- `app/Filament/User/Auth/SignIn/SignIn.php` (namespace `...Auth\SignIn`)
- `resources/views/User/social-buttons/auth-buttons/auth-buttons.blade.php`
  dipakai sebagai `User.social-buttons.auth-buttons.auth-buttons`

**Yang tidak ikut di-rename** (disengaja, karena ini kontrak eksternal):

- Nama route Filament: `filament.welcome.auth.login`, `filament.welcome.auth.index`,
  `filament.*.pages.home`.
- URL: `/auth`, `/signin`, `/admin/signin`, `/home`, URI API.
  Slug auth diatur lewat `loginRouteSlug('signin')` di masing-masing panel
  provider; URL lama (`/user/login`, `/user/signup`, `/user/register`,
  `/admin/login`) dilayani redirect di `routes/web/web.php` supaya bookmark lama
  tidak jadi 404 — `/user/signup` dan `/user/register` ke `/signup`,
  `/user/login` ke `/signin`. Kode
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
| `AuthenticateWelcome` | Gerbang panel welcome. Path publik (`welcome` (root), `/home`, `/flowerdecorationscatalog`, `/messages/{id?}`, ...) boleh dibuka guest; sisanya dilempar ke halaman auth panel welcome (`/auth`, Sign In + Google). Konstanta `LOGIN_ROUTE = 'filament.welcome.auth.index'`. |
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
