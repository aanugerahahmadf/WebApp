# Autentikasi (Sign-In & Auth Landing)

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

## Halaman Auth per Panel

| Panel | Sign-In | Auth Landing | Pendaftaran | Reset Kata Sandi | Catatan |
|---|---|---|---|---|---|
| Welcome | `User\Auth\SignIn\SignIn` (`/signin`) | `User\Auth\Auth\Auth` (`/auth`) | `User\Auth\SignUp\SignUp` (`/signup`) | OTP via WhatsApp/Email (`OtpRequestPasswordReset` + `OtpResetPassword`) + verifikasi OTP + prompt verifikasi email | Panel storefront dan akun aktif |
| Admin | `Admin\Auth\SignIn\SignIn` (`/admin/signin`) | — | — (tidak ada route `/admin/register`) | OTP, sama seperti user | Admin dibuat lewat seeder, bukan registrasi publik |
| Welcome | — | — | — | — | Menumpang ke `/auth` (Sign In + Google) lewat `AuthenticateWelcome::LOGIN_ROUTE` |

### Registrasi email/password

Panel `welcome` mendaftarkan `SignUp` secara aktif melalui
`->registration(SignUp::class)` dan `->registrationRouteSlug('signup')` pada
`WelcomePanelProvider`. URL `/signup` adalah route canonical. OAuth Google di
`/auth` tetap tersedia sebagai jalur alternatif dan dapat membuat akun baru.

Data tambahan yang tidak dikumpulkan pada form dasar pendaftaran dilengkapi di
`/complete-profile`. URL legacy `/register` dan `/user/register` diarahkan ke
route canonical melalui kompatibilitas web.

Slug route sign-in mengikuti penamaan class (`login` -> `signin`) lewat
`loginRouteSlug()` di `WelcomePanelProvider` dan `AdminPanelProvider`. **Nama route
tetap** `filament.welcome.auth.login` / `filament.admin.auth.login`, jadi semua
pemanggilan `route()`/`Filament::getLoginUrl()` tidak berubah. URL lama
(`/user/login`, `/user/register`, `/register`, `/admin/login`) dilayani
`Route::redirect` di `routes/web/web.php` supaya bookmark lama tidak jadi 404.

Panel admin **tidak** memakai halaman auth panel welcome: redirect ke sign-in
selalu lewat `route('filament.admin.auth.login')`, bukan URL yang ditulis
manual, supaya otomatis ikut slug baru.

Class Sign-In memperluas (`extends`) base page Filament
(`Filament\Pages\Auth\Login`); base class-nya tidak di-rename.

## Aturan Wajib di Sign-In User

Form login user mewajibkan dua checkbox (disimpan di field tersembunyi
`agreement` dan `remember`, divalidasi di `authenticate()`):

1. **Ingat Saya** (`remember`) — wajib dicentang.
2. **Persetujuan syarat & ketentuan** (`agreement`) — wajib dicentang.

Halaman `/auth` justru **tidak** memakai kedua checkbox itu: dia hanya
pintu masuk, bukan form persetujuan. Checkbox dirender terpisah di form
`/signin` (`View::make('User.social-buttons.agreement-checkboxes...')`).

Login memakai satu kolom `login` yang fleksibel: email, username, nomor KTP,
passport, SIM, atau NPWP. Label field-nya:
`KTP / Passport / SIM / NPWP / Username / Email`. Agar label panjang itu tetap
sebaris di desktop, wrapper field-nya memakai class `signin-identifier` dan
halaman sign-in melebarkan kartu (`max-w-lg` menjadi `36rem`) lewat CSS
terbatas di view `User.auth.sign-in.sign-in`. Ukuran font tetap bawaan Filament.

## Login Sosial & Tombol Auth

Blok tombol (`social-buttons`, terdiri dari `agreement-checkboxes`,
`agreement-modal`, `auth-buttons`, `social-buttons`) dipakai Sign-In lewat
`View::make(...)`. Tombol Google-nya sendiri hanya dirender di `/auth`
(flag `showGoogleButton`), supaya ada satu pintu OAuth saja. Alamat callback
Google: `/auth/google/redirect` (lihat `SocialiteController`).

## Tombol Kembali di Halaman Sign-In

Method `SignIn::getBackUrl()` memilih tujuan dengan urutan:

1. `url()->previous()` yang valid (sinyal terbaru — dari halaman mana user datang),
2. `session('url.intended')` (disimpan saat guest membuka modal Add to Cart atau aksi guest lain; tetap benar walau login sempat gagal lalu form di-render ulang),
3. fallback ke beranda welcome (`filament.welcome.pages.home`).

Kandidat yang menunjuk ke Livewire, halaman auth (`/signin`), route pendaftaran
(`/signup`, `/user/register`), OTP, atau
`/admin` dilewati. Path itu tidak ditulis manual:
`HasAuthBreadcrumbs::authPagePath()` dan `retiredRegisterPaths()` mengambilnya
dari slug route panel, jadi tetap benar kalau slug-nya diubah lagi. Prioritas
pertama diberikan untuk halaman katalog welcome (paket/produk — slug boleh
berubah, yang dicocokkan kata kuncinya), lalu halaman welcome lain.

CATATAN: `getBackUrl()` belum punya konsumen view -- tidak ada blade yang
memanggilnya, jadi tujuan yang dihitungnya belum muncul sebagai tautan di
halaman login. Satu-satunya jalan kembali dari halaman sign-in sekarang adalah
breadcrumb ke `/auth`. Method-nya tetap diuji (`SignInBackUrlTest`)
karena logikanya dipakai begitu tombolnya disambungkan.

## Setelah Login

- Notifikasi "Selamat Datang Kembali!" dikirim beserta info lokasi login
  (lihat listener `LoginEvent` di `AppServiceProvider`: update IP/kota/region/negara).
- Guard kustom `getCredentialsFromFormData()` memetakan kolom login ke field
  user yang cocok.
- Kegagalan login melempar notifikasi "Otentikasi Gagal" + `ValidationException`
  pada field login.
