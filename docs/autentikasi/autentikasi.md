# Autentikasi (Sign-In & Sign-Up)

## Halaman Auth per Panel

| Panel | Sign-In | Sign-Up | Reset Kata Sandi | Catatan |
|---|---|---|---|---|
| User | `User\Auth\SignIn\SignIn` (`/user/signin`) | `User\Auth\SignUp\SignUp` (`/user/signup`) | OTP via WhatsApp/Email (`OtpRequestPasswordReset` + `OtpResetPassword`) + verifikasi OTP + prompt verifikasi email | Lengkap |
| Admin | `Admin\Auth\SignIn\SignIn` (`/admin/signin`) | — (dihapus, tidak ada route `/admin/register`) | OTP, sama seperti user | Admin dibuat lewat seeder, bukan registrasi publik |
| Welcome | — | — | — | Menumpang ke login panel user |

Slug route auth mengikuti penamaan class (`login` -> `signin`,
`register` -> `signup`) lewat `loginRouteSlug()` / `registrationRouteSlug()`
di `UserPanelProvider` & `AdminPanelProvider`. **Nama route tetap**
`filament.user.auth.login` / `filament.user.auth.register` /
`filament.admin.auth.login`, jadi semua pemanggilan `route()`/
`Filament::getLoginUrl()` tidak berubah. URL lama (`/user/login`,
`/user/register`, `/admin/login`) dilayani `Route::redirect` di
`routes/web/web.php` supaya bookmark lama tidak jadi 404.

Panel admin **tidak** memakai halaman auth panel user: redirect ke sign-in
selalu lewat `route('filament.admin.auth.login')`, bukan URL yang ditulis
manual, supaya otomatis ikut slug baru.

Class Sign-In/Sign-Up memperluas (`extends`) base page Filament
(`Filament\Pages\Auth\Login`, `Filament\Pages\Auth\Register`); base class-nya
tidak di-rename.

## Aturan Wajib di Sign-In & Sign-Up User

Form login/registrasi user mewajibkan dua checkbox (disimpan di field
tersembunyi `agreement` dan `remember`, divalidasi di `authenticate()` /
`handleRegistration()`):

1. **Ingat Saya** (`remember`) — wajib dicentang.
2. **Persetujuan syarat & ketentuan** (`agreement`) — wajib dicentang.

Login memakai satu kolom `login` yang fleksibel: email, username, nomor KTP,
passport, SIM, atau NPWP. Label field-nya:
`KTP / Passport / SIM / NPWP / Username / Email`. Agar label panjang itu tetap
sebaris di desktop, wrapper field-nya memakai class `signin-identifier` dan
halaman sign-in melebarkan kartu (`max-w-lg` menjadi `36rem`) lewat CSS
terbatas di view `User.auth.sign-in.sign-in`. Ukuran font tetap bawaan Filament.

## Login Sosial & Tombol Auth

Blok tombol (`social-buttons`, terdiri dari `agreement-checkboxes`,
`agreement-modal`, `auth-buttons`, `social-buttons`) dipakai bersama oleh
Sign-In dan Sign-Up lewat `View::make(...)`. Alamat callback Google:
`/auth/google/redirect` (lihat `SocialiteController`).

## Tombol Kembali di Halaman Sign-In

Tombol panah-kembali di halaman login **tidak selalu ke beranda**. Method
`SignIn::getBackUrl()` memilih tujuan dengan urutan:

1. `url()->previous()` yang valid (sinyal terbaru — dari halaman mana user datang),
2. `session('url.intended')` (disimpan saat guest membuka modal Add to Cart atau aksi guest lain; tetap benar walau login sempat gagal lalu form di-render ulang),
3. fallback ke beranda welcome (`filament.welcome.pages.home`).

Kandidat yang menunjuk ke Livewire, halaman auth (`/user/signin`,
`/user/signup`, OTP), atau `/admin` dilewati. Path halaman auth tidak
ditulis manual: `HasAuthBreadcrumbs::authPagePath()` dan
`registrationPagePath()` mengambilnya dari slug route panel, jadi tetap
benar kalau slug-nya diubah lagi. Prioritas pertama diberikan untuk halaman
katalog welcome (paket/produk — slug boleh berubah, yang dicocokkan
kata kuncinya), lalu halaman welcome lain.

## Setelah Login

- Notifikasi "Selamat Datang Kembali!" dikirim beserta info lokasi login
  (lihat listener `LoginEvent` di `AppServiceProvider`: update IP/kota/region/negara).
- Guard kustom `getCredentialsFromFormData()` memetakan kolom login ke field
  user yang cocok.
- Kegagalan login melempar notifikasi "Otentikasi Gagal" + `ValidationException`
  pada field login.
