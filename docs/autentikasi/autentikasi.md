# Autentikasi (Sign-In & Auth Landing)

## Halaman Auth per Panel

| Panel | Sign-In | Auth Landing | Pendaftaran | Reset Kata Sandi | Catatan |
|---|---|---|---|---|---|
| User | `User\Auth\SignIn\SignIn` (`/user/signin`) | `User\Auth\Auth\Auth` (`/user/auth`) | **Tidak ada halaman** (Google only) | OTP via WhatsApp/Email (`OtpRequestPasswordReset` + `OtpResetPassword`) + verifikasi OTP + prompt verifikasi email | Pendaftaran lewat tombol Google di `/user/auth` |
| Admin | `Admin\Auth\SignIn\SignIn` (`/admin/signin`) | — | — (tidak ada route `/admin/register`) | OTP, sama seperti user | Admin dibuat lewat seeder, bukan registrasi publik |
| Welcome | — | — | — | — | Menumpang ke `/user/auth` (Sign In + Google) lewat `AuthenticateWelcome::LOGIN_ROUTE` |

### Registrasi email/password DINONAKTIFKAN (kodenya disimpan)

Baris pemanggil **tidak dihapus, hanya dikomentari** dengan `//`, dalam bentuk
aslinya. Yang dimatikan di `UserPanelProvider`:

```php
// use App\Filament\User\Auth\SignUp\SignUp;
// ->registration(SignUp::class)
// ->registrationRouteSlug('signup')
```

Dan di `AppServiceProvider` (bentuk aslinya, pakai alias `as UserSignUp`):

```php
// use App\Filament\User\Auth\SignUp\SignUp as UserSignUp;
// Livewire::component('app.filament.user.auth.sign-up', UserSignUp::class);
```

Jadi `App\Filament\User\Auth\SignUp\SignUp` beserta view
`User/auth/sign-up/sign-up` masih utuh di repo. Menghidupkan pendaftaran lagi
berarti menghapus keenam tanda `//` itu plus satu blok markup di
`resources/views/User/social-buttons/social-buttons/social-buttons.blade.php`.

`->registrationRouteSlug('signup')` wajib ikut dibuka — kalau terlewat,
`/user/signup` hilang tanpa jejak sementara `/user/register` tetap hidup
sebagai door yang tidak punya halaman. `RenameSmokeTest` dan
`UserAuthLandingPageTest` menjaga keenam baris itu tetap terkomentari selama
pendaftaran dinonaktifkan.

Konsekuensi selama dimatikan:

- Satu-satunya pintu masuk tamu adalah `/user/auth`, yang memuat dua pintu:
  tombol Sign In (form `/user/signin`) dan tombol Google.
- Tombol Google membuat akun otomatis kalau email-nya belum terdaftar
  (`SocialiteController`), lalu mengarahkan ke Complete Profile — jadi tidak
  ada pengguna yang terkunci tanpa jalan daftar.
- `/user/signup` dan `/user/register` dilayani `Route::redirect` ke `/user/auth`,
  supaya bookmark lama mendarat di tombol Google, bukan 404.
- Data yang dulu hanya ditanyakan di form pendaftaran (nama depan/tengah/belakang,
  unggah KTP/selfie/avatar, KYC) dirender di `/user/complete-profile`.
- `SignIn::registerAction()` tetap di-`->hidden()` supaya form sign-in tidak
  mewarisi link "Register" dari base class Filament.
- `HasAuthBreadcrumbs::retiredRegisterPaths()` mengenali `/user/signup` dan
  `/user/register` sebagai URL yang tidak boleh jadi crumb. Kalau pendaftaran
  dihidupkan, daftar itu harus ikut dikosongkan.

Slug route sign-in mengikuti penamaan class (`login` -> `signin`) lewat
`loginRouteSlug()` di `UserPanelProvider` & `AdminPanelProvider`. **Nama route
tetap** `filament.user.auth.login` / `filament.admin.auth.login`, jadi semua
pemanggilan `route()`/`Filament::getLoginUrl()` tidak berubah. URL lama
(`/user/login`, `/user/register`, `/user/signup`, `/admin/login`) dilayani
`Route::redirect` di `routes/web/web.php` supaya bookmark lama tidak jadi 404.

Panel admin **tidak** memakai halaman auth panel user: redirect ke sign-in
selalu lewat `route('filament.admin.auth.login')`, bukan URL yang ditulis
manual, supaya otomatis ikut slug baru.

Class Sign-In memperluas (`extends`) base page Filament
(`Filament\Pages\Auth\Login`); base class-nya tidak di-rename.

## Aturan Wajib di Sign-In User

Form login user mewajibkan dua checkbox (disimpan di field tersembunyi
`agreement` dan `remember`, divalidasi di `authenticate()`):

1. **Ingat Saya** (`remember`) — wajib dicentang.
2. **Persetujuan syarat & ketentuan** (`agreement`) — wajib dicentang.

Halaman `/user/auth` justru **tidak** memakai kedua checkbox itu: dia hanya
pintu masuk, bukan form persetujuan. Checkbox dirender terpisah di form
`/user/signin` (`View::make('User.social-buttons.agreement-checkboxes...')`).

Login memakai satu kolom `login` yang fleksibel: email, username, nomor KTP,
passport, SIM, atau NPWP. Label field-nya:
`KTP / Passport / SIM / NPWP / Username / Email`. Agar label panjang itu tetap
sebaris di desktop, wrapper field-nya memakai class `signin-identifier` dan
halaman sign-in melebarkan kartu (`max-w-lg` menjadi `36rem`) lewat CSS
terbatas di view `User.auth.sign-in.sign-in`. Ukuran font tetap bawaan Filament.

## Login Sosial & Tombol Auth

Blok tombol (`social-buttons`, terdiri dari `agreement-checkboxes`,
`agreement-modal`, `auth-buttons`, `social-buttons`) dipakai Sign-In lewat
`View::make(...)`. Tombol Google-nya sendiri hanya dirender di `/user/auth`
(flag `showGoogleButton`), supaya ada satu pintu OAuth saja. Alamat callback
Google: `/auth/google/redirect` (lihat `SocialiteController`).

## Tombol Kembali di Halaman Sign-In

Method `SignIn::getBackUrl()` memilih tujuan dengan urutan:

1. `url()->previous()` yang valid (sinyal terbaru — dari halaman mana user datang),
2. `session('url.intended')` (disimpan saat guest membuka modal Add to Cart atau aksi guest lain; tetap benar walau login sempat gagal lalu form di-render ulang),
3. fallback ke beranda welcome (`filament.welcome.pages.home`).

Kandidat yang menunjuk ke Livewire, halaman auth (`/user/signin`), door
pendaftaran yang sudah dinonaktifkan (`/user/signup`, `/user/register`), OTP, atau
`/admin` dilewati. Path itu tidak ditulis manual:
`HasAuthBreadcrumbs::authPagePath()` dan `retiredRegisterPaths()` mengambilnya
dari slug route panel, jadi tetap benar kalau slug-nya diubah lagi. Prioritas
pertama diberikan untuk halaman katalog welcome (paket/produk — slug boleh
berubah, yang dicocokkan kata kuncinya), lalu halaman welcome lain.

CATATAN: `getBackUrl()` belum punya konsumen view -- tidak ada blade yang
memanggilnya, jadi tujuan yang dihitungnya belum muncul sebagai tautan di
halaman login. Satu-satunya jalan kembali dari halaman sign-in sekarang adalah
breadcrumb ke `/user/auth`. Method-nya tetap diuji (`SignInBackUrlTest`)
karena logikanya dipakai begitu tombolnya disambungkan.

## Setelah Login

- Notifikasi "Selamat Datang Kembali!" dikirim beserta info lokasi login
  (lihat listener `LoginEvent` di `AppServiceProvider`: update IP/kota/region/negara).
- Guard kustom `getCredentialsFromFormData()` memetakan kolom login ke field
  user yang cocok.
- Kegagalan login melempar notifikasi "Otentikasi Gagal" + `ValidationException`
  pada field login.
