# tests/ — struktur dan konvensi

Suite ini memakai **Pest** (Pest 4) di atas Laravel 12 + Filament 3.

## Struktur folder

```
tests/
├── Feature/         satu komponen / satu Livewire page
│   ├── Admin/      <TestName>/<TestName>.php
│   ├── User/       <TestName>/<TestName>.php
│   ├── Welcome/    <TestName>/<TestName>.php
│   └── Shared/     <TestName>/<TestName>.php
├── Integration/     request HTTP nyata + seluruh rantai middleware + efek ke DB
│   ├── Http/       <TestName>/<TestName>.php
│   ├── Middleware/ <TestName>/<TestName>.php
│   ├── Database/   <TestName>/<TestName>.php
│   └── Shared/     <TestName>/<TestName>.php
├── Unit/            satu kelas, tanpa DB dan tanpa HTTP
│   ├── Platform/   <TestName>/<TestName>.php
│   ├── User/       <TestName>/<TestName>.php
│   └── Shared/     <TestName>/<TestName>.php
├── TestHelpers/
├── Pest.php        TestCase + RefreshDatabase untuk Feature dan Integration
├── TestCase.php
└── bootstrap.php
```

**Unit vs Feature vs Integration** -- pilih yang mana:

| | database | HTTP | yang diuji |
|---|---|---|---|
| `Unit` | tidak | tidak | satu kelas/function-nya |
| `Feature` | ya | ya | satu komponen, satu Livewire page, atau satu view |
| `Integration` | ya | ya | rangkaian middleware + route + efeknya ke DB |

Bedanya bukan sekadar "lebih besar". Test Feature memanggil `Livewire::test()` atau
satu request, jadi middleware yang tidak dipanggil di jalur itu tidak ikut
teruji. Test Integration mengirim request HTTP sungguhan, sehingga
`SetLocale`, `ClerkFilamentAuth`, `EnsureProfileComplete`, `AuthenticateWelcome`,
dan `SuperAdmin` ikut berjalan sesuai konfigurasi aslinya -- termasuk urutan dan
interaksinya. Bug yang hanya muncul karena urutan middleware tidak akan pernah
ketangkap di folder lain.

Aturan folder:

1. **Satu file test = satu folder.** Folder bernama sama dengan file di
   dalamnya. Alasannya: banyak file test di proyek ini punya header comment panjang
   plus helper function-nya sendiri, dan menumpuk semua file dalam satu folder
   membuat diff-nya saling menimpa.
2. **Folder pertama menyatakan panel yang diuji** (`User`, `Welcome`, `Admin`),
   atau `Shared` kalau test itu lintas panel / bukan panel sama sekali
   (middleware, service, API, autoloading).
3. Penemuan test tetap berjalan: `phpunit.xml` memindai `tests/Feature` dan
   `tests/Unit` secara rekursif, dan `Pest.php` memakai `->in('Feature')` yang
   juga rekursif. Menambah folder di dalam tidak perlu mengubah konfigurasi.
4. File yang berbasis class PHPUnit (bukan closure Pest) **wajib** memiliki
   namespace yang cocok dengan lokasinya, misalnya
   `tests/Feature/User/AuthTest/AuthTest.php` → `namespace Tests\Feature\User\AuthTest;`
   karena PSR-4 memetakan `Tests\` → `tests/`. `composer dump-autoload -o`
   akan memperingatkan kalau tidak sesuai.

## Menjalankan

```bash
php artisan test                                   # semua
php artisan test tests/Feature/User                # satu folder
php artisan test tests/Feature/User/SignInAuthenticationTest/SignInAuthenticationTest.php
php artisan test --filter="the guest dropdown"     # per nama test
```

> Catatan MySQL lokal: suite memakai database `testing` yang di-wipe oleh
> `RefreshDatabase`. **Jangan** menjalankan dua `php artisan test` bersamaan --
> keduanya akan saling men-drop tabel di database yang sama dan menghasilkan
> error palsu (`Table ... doesn't exist`, `Unknown column ...`).
>
> Pakai DB khusus kalau bisa, lalu arahkan `DB_DATABASE` ke situ supaya suite
> kebal dari proses lain:
>
> ```bash
> # PowerShell
> $env:DB_DATABASE='wedding_cbir_test'; php artisan migrate:fresh --force; php artisan test
> ```
>
> Gejala kalau DB-nya dipakai proses lain di tengah jalan: puluhan `QueryException`
> beruntun (`Unknown column 'two_factor_type'`, `Table '...users' doesn't exist`)
> yang **hanya muncul di sebagian test**, sementara test yang sama lolos kalau
> dijalankan sendiri. Jangan mencari bug di kode sebelum memastikan tidak ada
> `migrate:fresh` lain yang jalan bersamaan.

## Database: kenapa `.env.testing` wajib ada

`php artisan test` aman karena `phpunit.xml` mengunci `DB_DATABASE=testing`.

Yang **tidak** aman adalah perintah artisan yang dipanggil manual dengan
`--env=testing`:

```
php artisan migrate:fresh --env=testing --force
```

Laravel mencari `.env.testing` saat `--env=testing` diberikan. Kalau file itu
tidak ada, dia jatuh ke `.env` -- yang menunjuk database **dev**. Perintahnya
tetap terlihat "untuk testing", tapi yang di-drop adalah seluruh tabel
database dev beserta datanya.

`.env.testing` di repo ini karena itu. File-nya sendiri di-gitignore
(`.env.*`), jadi ikut lokal saja dan tidak pernah ter-commit. Isinya salinan
`.env` dengan `APP_ENV=testing`, `DB_DATABASE=testing`, dan cache/session/queue
dipindah ke `array` supaya suite tidak menulis ke cache atau queue sungguhan.

Cara memastikan sebelum menjalankan perintah destructive apa pun:

```
php artisan tinker --env=testing -r 'echo config("database.connections.mysql.database");'
```

Kalau keluar `testing`, aman. Kalau keluar nama database dev, jangan jalankan.

### Dua proses test tidak boleh jalan bersamaan

`RefreshDatabase` melakukan `migrate:fresh` di awal. Kalau proses test kedua
mulai selagi yang pertama masih berjalan, tabel di-drop dan dibuat ulang di
bawah kaki proses yang sedang jalan, dan hasilnya bukan satu test gagal tapi
puluhan kegagalan yang menyebar ke file yang tidak ada hubungannya sama sekali
(`WelcomePanelTest`, `PasswordSecurityPagesTest`, `FaceScanModalGuardTest`,
`OtpEmailOrTwoFactoryTest` sekaligus).

Tanda tangan yang muncul saat kejadian ini:

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'testing.roles' doesn't exist
PDOException: SQLSTATE[HY000]: General error: 1412 Table definition has changed, please retry transaction
```

`1412 Table definition has changed` adalah bukti paling jelas: hanya ada satu
cara tabel berubah definisinya di tengah transaksi -- proses lain yang
menjalankan migrate. Kalau tanda tangan ini muncul, **jangan buru-buru mencari
bug di kode produksi.** Periksa dulu apakah ada proses `php artisan test` lain
yang masih jalan.

Aturan: satu proses test pada satu database. Butuh menjalankan test tambahan
sambil suite penuh berjalan? Pakai database lain (`DB_DATABASE` berbeda), bukan
database yang sama.

### 3b. Test yang hanya gagal di suite penuh

`AuthTest > user can register` gagal dengan `422` dan pesan
`The password field format is invalid.` -- padahal file itu **sendiri** hijau
(22 passed). Itupun hanya terjadi di dalam suite penuh.

Dua sebab yang harus dibedakan, dan keduanya sudah pernah muncul di repo ini:

1. **Konkurensi.** Lihat bagian 3a. Gejalanya `QueryException`,
   `1412 Table definition has changed`, atau `hash_file(): Permission denied` --
   yang terakhir muncul karena dua proses membaca berkas yang sama pada waktu
   bersamaan di Windows.
2. **State global yang bocor antar-test.** Satu test mengubah
   `Password::defaults()`, `config([...])`, atau locale, dan test berikutnya
   mewarisi. Gejalanya jauh lebih halus: test gagal HANYA setelah test tertentu,
   dan pesannya berasa tidak nyambung dengan file yang gagal.

Cara membedakannya: jalankan file yang gagal sendirian dulu.

- Hijau sendiri -> bukan soal konkurensi; cari test lain yang mengubah state
  global, lalu telusuri urutan test yang membuatnya peka.
- Merah sendiri  -> masalahnya di file itu, bukan di suite.

Jangan "memperbaiki" test yang benar hanya supaya suite hijau. Kalau test itu
benar dan test lain yang merusak state-nya, yang diperbaiki adalah yang kedua --
dengan menambahkan `afterEach` yang mengembalikan state itu.


## Jebakan yang sudah pernah menggigit

### 1. `__()` di dalam `->with([...])` membuat seluruh suite mati

```php
// SALAH -- dataset jadi kosong, Pest melempar DatasetMissing
})->with([
    'request a reset' => ['http://localhost/.../request', __('Lupa Kata Sandi')],
]);

// BENAR -- dataset membawa KUNCI, terjemahan dihitung di body
})->with([
    'request a reset' => ['http://localhost/.../request', 'Lupa Kata Sandi'],
]);

test('...', function (string $origin, string $labelKey): void {
    expect($origin)->toBe(__($labelKey));
});
```

Gejalanya:

```
A test with the description [...] has [2] argument(s) and no dataset(s) provided
```

Yang membuatnya sulit: exception-nya dilempar **saat Pest mengumpulkan test**,
bukan saat test berjalan. Konsekuensinya bukan satu test gagal --
`php artisan test` **berhenti total** di file itu, dan test setelahnya tidak
sampai dijalankan sama sekali. Kalau suite terlihat "mati total" padahal
sebagian besar test-nya sehat, periksa dulu `->with([` yang memanggil `__()`.

Aturan umum: **dataset hanya boleh berisi literal.** Apa pun yang butuh
container Laravel (`__()`, `route()`, `now()`, factory) dipindah ke dalam body
test atau ke helper yang dipanggil setelah request.

### 2. Referer yang sama dengan halaman yang sedang dibuka

`HasAuthBreadcrumbs` membuang candidate yang sama dengan `url()->current()`.
Kalau helper test memakai path request tetap, maka origin yang sama dengan path
itu ikut terbuang -- dan test gagal dengan
`Trying to access array offset on null`, yang terlihat seperti bug produksi
padahal memang perilaku yang benar.

Jadi helper yang menyimulasikan "asal klik" harus memakai path request yang
tidak mungkin sama dengan origin yang diuji, dan itu ditulis di docblock
helper-nya.

### 3. Penanda komentar Blade di dalam teks komentar

Blade **tidak mendukung komentar bersarang**. Penutup `--}}` pertama yang
ditemukan akan menutup komentar, apa pun yang ditulis sebelum itu. Jadi kalimat
seperti "hapus pembungkus `{{--` / `--}}` ini" di dalam sebuah komentar Blade
akan menutup komentarnya sendiri di tengah kalimat, dan seluruh sisa paragraf
ikut dirender sebagai teks terlihat di halaman.

Untuk "menonaktifkan" blok markup, tulis penandanya dengan kata
("hapus dua pembungkus komentar Blade ini"), bukan dengan simbol aslinya.

Penjaga: `tests/Feature/Shared/BladeCommentLeakTest/BladeCommentLeakTest.php`.
Test itu memeriksa dua lapis karena sumber dan akibatnya bisa lolos terpisah:
simulasi parser Blade di file sumber (deteksi pembuka komentar kedua di dalam
badan komentar, dan komentar yang tak pernah ditutup), lalu pemeriksaan bahwa
HTML hasil render `/user/auth` dan `/user/signin` tidak memuat kata watchdog
dari blok yang dikomentari. Test terakhir adalah penyeimbangnya: "tidak bocor"
tidak boleh tercapai dengan menghapus bloknya, markup aslinya harus masih ada
di dalam komentar.

## Konvensi penulisan test

- **Pest closure** (`test('...', function (): void { ... })`) untuk test baru.
  Class PHPUnit gaya lama hanya ada di file yang sudah sejak awal
  (`AuthTest`, `OrderTest`, `PaymentTest`, `ProfileTest`, `FirestoreServiceTest`,
  dan beberapa `tests/Unit/Platform/*`).
- Nama test **bahasa Inggris**, kalimat penuh, lowercase di awal, tanpa titik di
  akhir: `test('a guest can open the auth landing page', ...)`.
- Tipe return wajib: `function (): void`, `function (string $path): void`.
- `uses(RefreshDatabase::class)` ditulis eksplisit di file Feature walau
  `Pest.php` sudah menerapkannya ke seluruh folder -- mengikuti konvensi yang
  berlaku.
- `beforeEach()` menyejajarkan state yang dipakai test: user + role, platform
  (`AppPlatform::fake(...)`), dan panel (`Filament::setCurrentPanel(...)`).
  `afterEach()` mengembalikan panel ke `null`.
- Dataset ditulis `->with([...])` dengan **array of array**
  (`['nama' => [$arg1, $arg2]]`), bukan peta asosiatif, karena Pest memosisikan
  argumen secara variadic.
- Komentar **bahasa Indonesia**, nama test dan string yang di-assert **bahasa
  Inggris**. Header setiap file menjelaskan (a) masalah nyata yang dijaga,
  (b) kegagalan mode yang harus hilang, dan (c) kenapa assertion-nya ditulis
  seperti itu -- bukan apa yang diassert.
- Kalau sebuah test diam-diam membatalkan cakupan (mis. gap yang diketahui
  masih ada di produksi), nyatakan terbuka di header file. Test yang menjebak
  lebih berbahaya daripada test yang jujur belum ada.

## Assertion

- Nilai: `expect()`.
- Response HTTP: `get()/post()` dari `Pest\Laravel`, lalu `assertOk()`,
  `assertRedirect()`, `assertSee()`.
- **HTML mentah** (`$response->getContent()`) dibandingkan dengan
  `expect($html)->toContain(...)` -- bentuk ini **tidak** meng-escape. Label
  yang berisi kutip atau `&` muncul di markup sebagai entity
  (`Don't` → `Don&#039;t`), jadi bandingkannya harus memakai bentuk yang sama.
  Dua jalan yang tersedia: `assertSee($label, escape: false)` atau helper
  `label()` yang membungkus `e(__($key))` -- dipakai di
  `SignInBreadcrumbsTest` dan `TopbarAccountControlTest`.
- Array string dan `toContain()`: expectation Pest bersifat variadic, jadi
  `toContain('pesan')` pada array string berarti "cari elemen bernama pesan".
  Untuk cek keanggotaan yang jelas, pakai
  `expect(in_array($needle, $haystack, true))->toBeTrue()`.
- Method halaman Filament dipanggil pada instance
  `(new ReflectionClass($page))->newInstanceWithoutConstructor()` --
  constructor Livewire butuh container page yang sudah di-boot, sedangkan
  `getBreadcrumbs()`, `getTitle()`, `getHeading()` tidak butuh state.
- Method `protected` (mis. `getCredentialsFromFormData()`) dipanggil lewat
  `ReflectionMethod::setAccessible(true)` pada instance yang sama.
- Widget/halaman yang lazy (`x-intersect`) perlu
  `Filament::setCurrentPanel(...)` sebelum dirender manual.

## Locale

- Locale suite tidak dikunci: `SetLocale` membacanya dari query `?locale=` /
  `?lang=`, `session('locale')`, header `Accept-Language`, lalu bahasa di
  database user. Test harus tahan terhadap semua kemungkinan itu.
- Konvensi untuk label: **hitung `__()` SETELAH request**, lalu bandingkan
  dengan bentuk yang sudah di-escape (`e(...)` atau `escape: false`).
- Untuk mengunci dua bahasa secara eksplisit, buat dua request:
  `?locale=id` untuk wording Indonesia sebagai literal, `?locale=en` untuk
  wording Inggris lewat `__()`. Kunci yang hilang di salah satu file JSON akan
  membuat halaman Inggris diam-diam menampilkan Bahasa Indonesia -- dan itulah
  yang perlu digagalkan.
- Alur produksi switcher bukan `?locale=`, melainkan `LanguageController`
  (route `language.update`) yang menulis session; test label memakai jalur query
  karena middleware memperlakukannya sebagai sumber prioritas pertama.

## Test yang sengaja tidak ada

Beberapa perilaku memang belum punya test dan itu tercatat di header file yang
berkaitan (bukan di file terpisah), misalnya:

- `?locale=` tanpa validasi terhadap daftar locale yang didukung.
- **Halaman Sign Up / Register di panel User DINONAKTIFKAN, kodenya disimpan.**
  Baris pemanggilnya tidak dihapus, hanya dikomentari dengan `//` dalam
  bentuk aslinya: `use App\Filament\User\Auth\SignUp\SignUp;`,
  `->registration(SignUp::class)`, dan
  `->registrationRouteSlug('signup')` di `UserPanelProvider`, plus import dan
  alias `Livewire::component('app.filament.user.auth.sign-up', UserSignUp::class)`
  di `AppServiceProvider`. `app/Filament/User/Auth/SignUp` beserta view-nya
  masih utuh, jadi pendaftaran bisa dihidupkan lagi tanpa menulis ulang form.
  `SignIn::registerAction()` juga tetap di-`->hidden()`. Pendaftaran aktif hanya
  lewat tombol Google di `/user/auth` (`SocialiteController` membuat akun
  otomatis). Konsekuensinya, dipin oleh test:
  - `filament.user.auth.register` **tidak terdaftar**,
  - `/user/signup` dan `/user/register` redirect ke `/user/auth` (bukan 404),
  - tidak ada halaman auth yang lagi menautkan ke door pendaftaran (markup-nya
    ada sebagai Blade comment di `social-buttons`),
  - crumb "Sign Up" tidak ada di `HasAuthBreadcrumbs` -- sebelum cabangnya
    dihapus, cabang itu memanggil `route()` yang hilang dan membuat halaman auth
    500 untuk tamu yang datang dengan referer `/user/signup`. URL lama itu kini
    hanya dikenali lewat `HasAuthBreadcrumbs::retiredRegisterPaths()` supaya
    `SignIn::getBackUrl()` tidak menunjuk ke halaman yang tidak ada.
  - Field yang tadinya ada di form pendaftaran (nama depan/tengah/belakang,
    unggah KTP/selfie/avatar, KYC) dirender di `/user/complete-profile`.
  - `RenameSmokeTest` menjaga dua sisi sekaligus: class + view SignUp harus
    **ada**, route register harus **tidak ada**.
  - `UserAuthLandingPageTest` membaca `UserPanelProvider.php` dan
    `AppServiceProvider.php`, lalu memastikan keempat baris pemanggilnya masih
    berkomentar `//` -- jadi "dimatikan" tidak bisa diam-diam berubah jadi
    "dihapus" atau "terdaftar".
- Pencocokan kolom `email` yang case-sensitive di
  `SignIn::getCredentialsFromFormData()`, sehingga email yang diketik huruf
  kapital tidak bisa login.
- `SignIn::loginAction()` memanggil `parent::loginAction()` yang tidak ada di
  Filament 3.3; method itu sudah tidak bisa dipanggil tanpa melempar
  `BadMethodCallException`.

## Perilaku produksi yang hanya terlihat karena test

Sembilan bug di bawah ditemukan oleh test, bukan oleh laporan pengguna. Semuanya
sudah diperbaiki; daftar ini ada supaya tidak ada yang mengubahnya kembali
karena "kayaknya cuma detail".

- **Render hook Filament itu global, bukan per-panel.**
  `panels::simple-page.start` dikirim dengan scope berupa *class halaman*
  (`BasePage::getRenderHookScopes()`), bukan id panel. Hook breadcrumb yang
  didaftarkan di `UserPanelProvider` karena itu ikut terpakai di halaman auth
  panel admin/welcome, dan partial `User/auth/breadcrumbs` memanggil
  `$page->getBreadcrumbs()` tanpa penjaga `method_exists()` -- Sign-In panel admin
  jadi **500**. Penjaganya ada di partial, bukan di provider, karena hook tidak
  bisa di-scope ke panel.
- **`redirect()` di dalam action Livewire bukan `Illuminate\Http\RedirectResponse`.**
  Livewire menukar binding `redirect` di container dengan `Redirector`-nya
  sendiri, yang tidak punya `getTargetUrl()` dan tidak cocok dengan return type
  `?RedirectResponse`. `ProductResource`/`PackageResource` (panel Welcome)
  memanggilnya dari action wishlist/cart/chat, sehingga **setiap tamu** yang
  menekan tombol itu mendapat TypeError. Solusinya bukan facade `Redirect`
  (ia juga membaca binding yang sama), tapi `new RedirectResponse(...)` untuk
  pemanggil yang butuh `getTargetUrl()`.
- **`TypeError` tidak tertangkap `catch (\Exception)`.** Checkout di keempat
  resource memanggil `ChatService::sendOrderMessage()` dengan inbox yang bisa
  null (`getOrCreateInboxWithAdmin()` mengembalikan null kalau belum ada
  super_admin). TypeError adalah `Error`, bukan `Exception`, jadi blok
  `catch (\Exception $e)` di sana tidak menangkapnya dan checkout jadi **500**
  tepat di Resource tanpa admin. Sekarang ada penjaga `if ($inbox)` plus
  `catch (\Throwable)` -- bentuk yang sama sudah dipakai `Api/User/OrderController`.
- **`HasMobilePagination` dulu trait mati.** Tidak ada halaman admin yang
  memakainya, jadi dua test paginasi panel admin gagal. Sekarang trait itu
  dipakai 19 halaman list/manage di `app/Filament/Admin/**/Pages/*`.
- **`SessionGuard::ensureRememberTokenIsSet()` hanya memutar `remember_token` yang
  NULL.** `UserFactory` mengisinya sejak awal, jadi test "remember me" tidak bisa
  diassert dengan "token berubah" maupun "token null" -- yang diuji adalah
  login berhasil dan token tidak berubah saat login ditolak.
- **Dua salinan `face-scan-modal` (User & Welcome) pernah berbeda jauh.**
  Wrapper fallback `window.ScannerUI` hanya ada di salinan User, jadi modal scan
  di panel Welcome bisa tampil tapi tidak punya helper saat bundle JS belum
  termuat. `FaceScanModalGuardTest` menjaga keduanya identik byte per byte.
- **`SetLocale` menulis `model_type` dengan kunci yang salah.** Dia memakai
  `get_class($user)` yang menghasilkan `App\Models\User\User`, sementara
  `User` memakai trait `LegacyMorphClass` sehingga `getMorphClass()` mengembalikan
  `App\Models\User`. Relasi `InteractsWithLanguages::lang()` mencari dengan
  `getMorphClass()`, jadi baris bahasa yang ditulis **tidak pernah ditemukan
  lagi**: pilihan bahasa pengguna tersimpan, lalu setiap pembacaan berikutnya
  jatuh ke default `'en'`. Efeknya pilihan bahasa tidak pernah berlaku -- persis
  fitur yang paling mungkin dilaporkan sebagai "kok nggak nyimpan?".
  Penjaga: `tests/Integration/Database/UserLanguagePersistenceTest`.
  Test Feature tidak akan menangkapnya, karena yang perlu dicek adalah isi tabel
  setelah request, bukan nilai yang dikembalikan request itu sendiri.
- **`SetLocale` menulis `?locale=<apa saja>` ke database tanpa penyaringan.**
  Branch `Accept-Language` memvalidasi terhadap daftar locale yang didukung,
  tapi branch query parameter tidak pernah. Nilai yang tidak ada di switcher ikut
  tersimpan di `user_languages`, dan karena baris itu terbaca sebagai preferensi
  pada kunjungan berikutnya, useraro tertahan di locale yang tidak punya berkas
  terjemahan. Sekarang kedua branch menyaring dengan daftar yang sama. Penjaga:
  test yang sama seperti di atas.
- **`IdentityVerification::markVerified()` mengisi `liveness_completed` tanpa
  syarat.** Kolom itu ditulis `true` di luar percabangan, sehingga respons
  FaceNet yang GAGAL (`success=false`), exception, atau array kosong tetap
  menandai pengguna sebagai lolos liveness padahal tidak ada pemeriksaan wajah
  yang pernah terjadi -- persis hole yang kelas itu dibuat untuk menutup.
  `identity_verified_at` sudah dijaga benar, tapi status liveness tidak.
  Penjaga: `tests/Unit/Shared/IdentityVerificationTest`.

## Memeriksa JavaScript dari PHP

Parse markup adalah batas antar bahasa. Tiga aturan yang dipakai suite setelah
`FaceScanModalGuardTest`:

- **Nilai atribut harus di-decode entity HTML dulu.** Blade menulis `{{ $var }}`
  sebagai `&quot;`, jadi browser menerima kutip yang sudah di-decode -- itulah yang
  dievaluasi Alpine.
- **Bungkus sesuai jenis atributnya.** `x-data` adalah expression
  (`new Function("return (" + nilai + ")")`), `x-init` adalah statement
  (`new Function(nilai)`). Membungkus `x-data` sebagai badan fungsi akan salah
  melapor: `{ isOpen: false }` dibaca sebagai label dan gagal di tanda titik dua.
- Pakai parser sungguhan (`node -e`), **jangan** `eval()` PHP: grammar keduanya
  berbeda, dan `ParseError` dari `eval()` tidak tertangkap `@` sehingga test mati
  dengan error, bukan pesan yang berguna.
- Assertion atas file Blade sebaiknya mengunci **perilaku** (state mana yang
  tercapai, jalur error apa yang ada), bukan bentuk baris pertama sebuah method:
  refactor yang sah --mis. menambah token anti-race sebelum
  `cameraState = 'starting'`-- tidak boleh reddenkan test.
