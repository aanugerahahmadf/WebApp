# tests/ ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â struktur dan konvensi

Suite ini memakai **Pest** (Pest 4) di atas Laravel 12 + Filament 3.

## Struktur folder

```
tests/
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Feature/         satu komponen / satu Livewire page
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Admin/      <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ User/       <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Welcome/    <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬ÂÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Shared/     <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Integration/     request HTTP nyata + seluruh rantai middleware + efek ke DB
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Http/       <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Middleware/ <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Database/   <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬ÂÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Shared/     <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Unit/            satu kelas, tanpa DB dan tanpa HTTP
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Platform/   <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ User/       <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬Å¡   ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬ÂÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Shared/     <TestName>/<TestName>.php
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ TestHelpers/     helper yang dipakai lebih dari satu file test
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Pest.php        TestCase + RefreshDatabase untuk Feature dan Integration
ÃƒÂ¢Ã¢â‚¬ÂÃ…â€œÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ TestCase.php    base class: isolasi view per proses + suppressor notice
ÃƒÂ¢Ã¢â‚¬ÂÃ¢â‚¬ÂÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ bootstrap.php   bootstrap PHPUnit (dipakai phpunit.xml)
```

**`tests/bootstrap.php` itu dipakai, bukan sisa.** `phpunit.xml` menunjuknya
lewat `bootstrap="tests/bootstrap.php"`. Dulu file itu tidak pernah dimuat
(`phpunit.xml` menunjuk `vendor/autoload.php`), jadi isinya cuma kode mati.
Sekarang isinya: require autoloader + isolasi hasil kompilasi Blade per
proses (`Tests\TestHelpers\CompiledViews`).

Yang TIDAK ada di sana: `set_error_handler`. Alasannya bukan sekadar
"biarkan PHPUnit yangurus" -- ada dua jebakan, dan keduanya sudah diuji:

| Jebakan | Gejala |
|---|---|
| Handler dipasang di bootstrap | `PHPUnit\Runner\ErrorHandler::enable()` BERHENTI kalau sudah ada handler yang terpasang (`if ($oldErrorHandler !== null) { restore_error_handler(); return; }`). PHPUnit jadi TIDAK memasang handler-nya, dan error PHP yang biasanya jadi test failure (E_WARNING, E_ERROR) berubah jadi tulisan biasa di output |
| Handler dipasang di `setUp()`, di atas handler PHPUnit | Untuk meneruskan error yang tidak dikenali, ia memanggil handler PHPUnit secara manual. PHPUnit mencari test case yang sedang jalan lewat `debug_backtrace()`, dan frame tambahan itu menggagalkan atribusinya -- deprecation yang seharusnya dikumpulkan PHPUnit tercetak mentah: `PHP Deprecated: ... in vendor/filament/support/src/View/ViewManager.php on line 63` |

Jadi suite ini **tidak menekan apa pun**. Dua pesan yang dulu dimaksudkan
ditekan di `tests/bootstrap.php` (`tempnam()` di `AliasLoader`, dan
`PDO::MYSQL_ATTR_SSL_CA` yang deprecated) memang tidak pernah muncul:
`config/database.php` sudah memakai `PDO\Mysql::ATTR_SSL_CA` yang ada sejak
PHP 8.4, jadi konstanta lama tidak pernah dievaluasi.

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
   `tests/Feature/User/AuthTest/AuthTest.php` ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ `namespace Tests\Feature\User\AuthTest;`
   karena PSR-4 memetakan `Tests\` ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ `tests/`. `composer dump-autoload -o`
   akan memperingatkan kalau tidak sesuai.

## Menjalankan

```bash
php artisan test                                   # semua
php artisan test tests/Feature/User                # satu folder
php artisan test tests/Feature/User/SignInAuthenticationTest/SignInAuthenticationTest.php
php artisan test --filter="the guest dropdown"     # per nama test
```

> **Database suite: SQLite in-memory.** `phpunit.xml` mengunci
> `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:`, jadi `php artisan test`
> **tidak pernah menyentuh database MySQL dev** -- tidak ada `migrate:fresh`
> yang jalan di sana, dan `RefreshDatabase` hanya membangun ulang skema di
> memori. Dua proses test yang jalan bersamaan karena itu tidak saling
> men-drop tabel.
>
> Yang tetap tidak aman adalah perintah artisan manual dengan `--env=testing`
> -- lihat bagian "Database: kenapa `.env.testing` wajib ada" di bawah.
>
> Jalankan satu proses test pada satu waktu kalau bisa. Yang paling sering
> jadi penyebab adalah **CPU**: dua proses suite penuh di satu laptop membuat
> test yang punya batas waktu ikut gagal padahal kodenya benar.

## Database: kenapa `.env.testing` wajib ada

`.env.testing` tidak dipakai oleh `php artisan test` (suite memakai SQLite
in-memory). File itu ada untuk perintah artisan yang memanggil `--env=testing`
secara manual:

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
php artisan db:show --env=testing
```

Lihat baris **Database** di keluarannya. Kalau keluar `testing`, aman. Kalau
keluar nama database dev, jangan jalankan.

(Jangan pakai `php artisan tinker -r '...'` -- opsi `-r` sudah tidak ada di
Laravel 12, jadi perintahnya gagal dengan `The "-r" option does not exist.`
yang tidak menjelaskan apa pun.)

### Dua proses test sekaligus: yang sudah diisolasi, yang belum

Suite sekarang aman dari dua proses yang jalan bersamaan. Dua hal yang
pernah dipakai bersama sudah dipisah:

| Yang dipakai bersama | Sekarang | Kenapa |
|---|---|---|
| Database | SQLite `:memory:` per proses | `phpunit.xml` mengunci `DB_CONNECTION` + `DB_DATABASE` (sudah sejak awal) |
| Hasil kompilasi Blade | `storage/framework/views/testing-<pid>` per proses | `Tests\TestHelpers\CompiledViews` (baru) |

Tanda tangan dari zaman saat keduanya masih berbagi state:

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'testing.roles' doesn't exist
PDOException: SQLSTATE[HY000]: General error: 1412 Table definition has changed, please retry transaction
```

`1412 Table definition has changed` adalah bukti paling jelas: hanya ada satu
cara tabel berubah definisinya di tengah transaksi -- proses lain yang
menjalankan migrate. Kalau tanda tangan ini muncul, **jangan buru-buru mencari
bug di kode produksi.** Periksa dulu apakah ada proses `php artisan test` lain
yang masih jalan.

Tanda tangan yang lain -- dan yang lebih sering muncul di Windows -- adalah
kegagalan menulis hasil kompilasi view:

```
rename(...\views\81a1B04.tmp, ...\views\81a27ff3....php): Access is denied (code: 5)
```

Kode 5 itu `ERROR_ACCESS_DENIED` di Windows: destination sedang dipegang
proses lain. Gejalanya muncul sebagai `ViewException` dari dalam Filament,
beberapa lapis dari penyebabnya, jadi terlihat seperti bug produksi. Folder
hasil kompilasi per proses menghilangkannya.

Yang **belum** terisolasi: CPU dan file di luar database. Dua suite penuh
sekaligus tetap bisa membuat test yang punya batas waktu gagal, dan test yang
menulis ke disk yang sama (media, export) bisa saling menimpa. Jadi aturan
"satukan proses test pada satu waktu" tetap berlaku -- hanya alasannya sekarang
bukan database.

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

### Panel 'user' sudah dilebur ke 'welcome' -- `getPanel('user')` TIDAK error

Sejak 2026-10-21 hanya ada dua panel: `welcome` (path `/`) dan `admin`
(path `/admin`). `UserPanelProvider` **tidak lagi terdaftar** di
`bootstrap/providers.php`; file-nya masih ada sebagai rujukan, tapi bukan
sumber kebenaran.

```php
// TIDAK error -- PanelRegistry::get() mengembalikan panel DEFAULT kalau
// id-nya tidak terdaftar, dan default-nya sekarang 'welcome'.
Filament::getPanel('user');   // -> objek panel 'welcome'

// INI yang error -- route prefix lama sudah tidak ada.
route('filament.user.pages.home');   // RouteNotFoundException
```

Akibatnya kode lama yang menulis `Filament::getPanel('user')` **tidak langsung
gagal** -- dia diam-diam memakai panel `welcome`. Route-nya ikut ke panel yang
benar, jadi sering kali test tetap hijau; yang salah biasanya terlihat
beberapa langkah kemudian, atau tidak sama sekali.

Yang boleh jadi rujukan:

- `Filament::getPanel('welcome')` (atau `getPanel()` tanpa argumen).
- `route('filament.welcome....')`.
- `Route::has('filament.welcome....')`.

Cek `php artisan route:list`, bukan `getPanel()`, kalau sedang mencari tahu
apakah suatu route lama masih hidup.

### 0. Role harus menyebut guard-nya

```php
// SALAH -- role jadi ber-guard `web`
Role::firstOrCreate(['name' => 'super_admin']);
Admin::factory()->superAdmin();  // RoleDoesNotExist: no role `super_admin` for guard `admin`

// BENAR -- lewat helper, guard mengikuti model
seedRoles();                                     // admin: super_admin, web: customer
seedRoles(adminRoles: [], webRoles: ['super_admin']);  // untuk User di guard web
```

Pakai `seedRoles()` dari `tests/Pest.php`. Role admin hidup di guard `admin`
(tabel `admins`), role shopper di guard `web` (tabel `users`), dan
`Model::assignRole()` mencari di guard model itu sendiri.

`makeAdminWithStorefrontIdentity()` untuk admin yang juga harus punya
identitas belanja (`admins.user_id`), karena `ChatStaff::adminUser()` mencari
`Admin::whereNotNull('user_id')`.

### 0b. Override `name` pada `Catalog::factory()` tidak berefek

```php
// TIDAK BERKEFEK -- yang tampil tetap nama dari daftar produk di factory
Catalog::factory()->create(['name' => 'Bunga Mawar']);

// BENAR -- accessor `name` membaca translations dulu
Catalog::factory()->create([
    'name_translations' => ['id' => 'Bunga Mawar', 'en' => 'Red Rose'],
]);
```

Tidak ada error dan baris tetap tersimpan -- gejalanya hanya assertion yang
membandingkan nama jadi gagal jauh dari penyebabnya. Lihat catatan di
`CatalogFactory`.

### 0c. `APP_URL` wajib dikunci di `phpunit.xml`

`route()` dan `Page::getUrl()` mengembalikan URL ABSOLUT, jadi nilainya ikut
`APP_URL`. Tanpa kunci di `phpunit.xml`, `assertSee(route(...))` membandingkan
host dari `.env` mesin itu: hijau di satu mesin, merah di mesin lain, untuk
kode yang sama persis.

### 0d. CAPTCHA harus diisi pada setiap pemanggilan `authenticate()`

`CaptchaField` menulis `session('filament_captcha_code')` dari dalam
`render()`, dan `render()` jalan pada SETIAP request Livewire. Kode yang di-set
sebelum `Livewire::test()` ditimpa di render pertama; kode yang dibaca setelah
`fillForm()`/`set()` sudah basi.

Pakai helper dari `tests/Pest.php`:

```php
fillCaptcha($component)->call('authenticate');
```

Helper ini menyamakan session dan field tepat sebelum `call()`, tanpa ada
request Livewire di antaranya. Menebak kode yang "kelihatan valid" tidak
berhasil: `Captcha` membandingkan dengan session, bukan dengan format, dan
pesan errornya sama untuk semua nilai -- jadi test tidak bisa membedakan "kode
salah" dari "sesi basi".

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
  (`Don't` ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ `Don&#039;t`), jadi bandingkannya harus memakai bentuk yang sama.
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

## Refactor `Product` -> `Catalog`: apa yang hilang, dan bagaimana cara mengatakannya

Penataan ulang itu menghapus `ProductResource` dan `PackageResource` di ketiga
panel, dan `Package` sekarang adalah TIER dari katalog
(`Catalog::tiers()` -> `hasMany(Package, 'catalog_id')`). Akibatnya banyak test
yang memanggil class atau URL yang sudah tidak ada. Semuanya diperlakukan
dengan aturan yang sama:

- **Test yang menguji hal yang MASIH ADA tapi lewat class/URL mati** -> arahkan
  ke resource yang benar, dan tulis di docblock kenapa class lama tidak bisa
  dipakai.
- **Test yang hanya mengulang test katalog dengan label "paket"** -> HAPUS, dan
  tulis alasannya. Menyalinnya akan menghasilkan dua test dengan nama berbeda
  yang menguji hal yang sama -- lebih buruk dari tidak ada, karena suite terlihat
  punya cakupan padahal tidak.
- **Test untuk fitur yang benar-benar tidak ada lagi** -> `->skip()` dengan
  alasan tertulis, DAN tambahkan test penjaga yang tidak di-skip. Penjaga itu
  membaca `class_exists()` dan akan GAGAL begitu fiturnya dibangun kembali --
  supaya saat itu ada yang mengingatkan untuk mengaktifkan kembali test yang
  di-skip, bukan diam-diam tetap hijau selamanya.

Contoh: `FilamentCheckoutVirtualAccountTest` menguji checkout paket, dan
`PackageResource`-nya sudah tidak ada. `CheckoutCatalog` tidak bisa
menggantikannya karena `Package` bukan turunan `Catalog`: `resolveRecord()`
mencari di tabel `catalogs`.

### Test penjaga statis

`tests/Unit/Shared/MissingImportsTest` memindai seluruh `app/` untuk kelas milik
project yang dipanggil tanpa import. Ini menjaga bug yang sudah beberapa kali
terjadi: satu baris `use` hilang saat refactor, PHP tidak melempar error sampai
class itu benar-benar dipanggil, dan errornya muncul jauh dari sumbernya
(`ViewException` dari dalam Filament).

Dua detail yang membuatnya berguna, dan keduanya sudah sempat membuat test ini
hijau tanpa mengecek apa pun:

1. **Komentar harus dibuang dulu.** Repo ini lebih banyak docblock daripada
   kode; tanpa pembuangan komentar setiap referensi di dalam komentar dilaporkan
   sebagai pelanggaran, dan 73 false positive langsung membuat orang
   mengabaikannya.
2. **Class dicari di seluruh `app/`, bukan di `app/<Name>.php`.**
   `CatalogResource` tidak ada di `app/CatalogResource.php`.

Yang ditemukan dan sudah diperbaiki: `FcmChannel` di `CustomNotification`
(import hilang -> fatal saat notifikasi dikirim), `CatalogResource` di dua
`VoucherResource`, `VerifyOtp` di empat halaman OTP, `CbirResultService` di tiga
Livewire Messages, `Notification` di `TransactionsRelationManager`, dan
`CatalogResource` di dua `HistoryResource`.

Dua false positive yang **diketahui** dan sengaja tidak dilaporkan, karena
setelah diperiksa manual keduanya benar-benar aman. Keduanya didokumentasikan
di kode supaya orang tidak "memperbaiki" hal yang tidak rusak:

- `TextColumn::make('value.bypass_secret')` -- dot notation pada atribut yang
  di-cast array. Ini membaca `$record->value['bypass_secret']`, jadi yang
  di-render selalu scalar. Pola ini disengaja dan dipakai
  `MaintenanceResource`.
- `TextColumn::make('label')` di `ReferenceOptionResource` -- dua kolom dengan
  nama sama, masing-masing untuk locale berbeda, dan **keduanya** memakai
  `->state(fn ($record) => $record->getLabelForLocale(...))`. Tool yang
  memeriksa per-blok bisa melewatkan pengaman di kolom kedua.

## Test yang sengaja tidak ada

Beberapa perilaku memang belum punya test dan itu tercatat di header file yang
berkaitan (bukan di file terpisah), misalnya:

- `?locale=` tanpa validasi terhadap daftar locale yang didukung.
- **Halaman Sign Up / Register sudah HIDUP lagi.** Catatan lama yang
  menyebutnya "DINONAKTIFKAN, kodenya disimpan" sudah usang. Yang benar
  sekarang:
  - Panggilannya ada di **`WelcomePanelProvider`**. Panel 'user' dilebur ke
    panel 'welcome' pada 2026-10-21, jadi `UserPanelProvider` -- yang masih
    ada sebagai file, tapi sudah **tidak terdaftar** di
    `bootstrap/providers.php` -- bukan lagi sumber kebenaran. Yang aktif:
    `->registration(SignUp::class)` dan `->registrationRouteSlug('signup')`.
  - `filament.welcome.auth.register` **terdaftar**, URL utamanya `/signup`.
  - Alias lama dilayani redirect di `routes/web/web.php`:
    `/register` dan `/user/register` -> `/signup`, `/login` dan `/user/login`
    -> `/signin`.
  - `SignIn::registerAction()` tetap di-`->hidden()`, jadi form Sign In tidak
    menampilkan tombol register bawaan Filament. Pintu pendaftaran ada di
    halaman Sign In (`/signin`) dan di item menu topbar storefront (`/home`).
    Halaman auth landing `/auth` justru **tidak** menautkan ke sana -- halaman
    itu hanya punya dua pintu: Sign In dan Google (lihat
    `UserAuthLandingPageTest`).
  - Crumb "Sign Up" **ada lagi** di `HasAuthBreadcrumbs` -- cabangnya memanggil
    `route('filament.welcome.auth.register')`, jadi halaman auth tidak lagi 500
    untuk tamu yang datang dengan referer `/signup`.
  - Field yang tadinya ada di form pendaftaran (nama depan/tengah/belakang,
    unggah KTP/selfie/avatar, KYC) dirender di `/complete-profile`.
  - `RenameSmokeTest` menjaga dua sisi sekaligus: class + view SignUp harus
    **ada**, route register storefront harus **terdaftar**, dan route register
    panel admin harus **tidak** ada.
  - `UserAuthLandingPageTest` membaca `WelcomePanelProvider.php` (provider yang
    benar-benar terdaftar) plus `bootstrap/providers.php`, lalu memastikan
    pendaftaran hidup dan `UserPanelProvider` tidak mendaftarkan diri lagi --
    jadi panel yang mati tidak bisa diam-diam jadi sumber kebenaran.
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
  `?RedirectResponse`. `CatalogResource`/`PackageResource` (panel Welcome)
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