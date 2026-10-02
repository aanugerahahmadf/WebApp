# Panduan Pengujian

## Cara Menjalankan

```powershell
# Seluruh suite
php artisan test

# Satu file
php artisan test tests/Feature/SignInBackButtonTest.php

# Beberapa file
php artisan test tests/Feature/SignInBackButtonTest.php tests/Feature/RenameSmokeTest.php tests/Feature/GuestAddToCartTest.php

# Satu kasus (filter nama test)
php artisan test tests/Feature/WelcomeChatAdminTest.php --filter="wishlist_detail redirects a guest"
```

Konfigurasi test ada di `phpunit.xml`: `APP_ENV=testing`, database memakai
koneksi default, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`,
`MAIL_MAILER=array`.

## Struktur

Framework memakai **Pest**. File test mengikuti konvensi satu-folder-per-file
seperti kode aplikasi:

- `tests/Feature/` — test alur HTTP & Livewire (otomatis memakai
  `RefreshDatabase`). Contoh pola baku:

  ```php
  uses(RefreshDatabase::class);

  beforeEach(function (): void {
      Role::firstOrCreate(['name' => 'customer']);
      $this->customer = User::factory()->create();
      $this->customer->assignRole('customer');
      Filament::setCurrentPanel(Filament::getPanel('welcome'));
  });
  ```

- `tests/Unit/` — test logika murni tanpa database.
- `tests/TestHelpers/` — helper bersama (contoh: `PlatformTestHelper`).

## Pola yang Sering Dipakai

| Kebutuhan | Cara |
|---|---|
| Halaman sebagai guest | `$this->get($url)->assertOk()` |
| Halaman butuh login | `actingAs($user, 'web')` lalu `get(...)` |
| Aksi infolist Filament | `Livewire::test(Page::class, ['record' => $id])->callInfolistAction('.namaActionAction', 'namaAction', [data])` |
| Redirect Livewire | `->assertRedirect(route(...))` |
| Cek DB | `$this->assertDatabaseHas('carts', [...])` |
| URL halaman Filament | `Resource::getUrl('view', ['record' => $id])` — **wajib** dipakai agar test kebal terhadap perubahan slug resource |

## Batasan yang Diketahui

1. **Helper test Filament crash saat action me-redirect.** Memanggil
   `callInfolistAction()` pada action guest yang langsung redirect
   (`wishlist_detail`, dsb.) menyebabkan `ErrorException: Attempt to read
   property "mountedInfolistActions" on null` di `TestsActions.php` vendor.
   Ini keterbatasan versi Filament/Livewire yang dipakai, bukan bug aplikasi.
   Solusinya: uji modal-nya (`mountInfolistAction` + `assertSee` link login)
   dan uji guard-nya langsung (`Resource::redirectGuestToLogin()`), seperti
   pada `GuestAddToCartTest`.
2. **Jangan hardcode slug katalog** (`/welcome/packages`, `/welcome/products`)
   karena slug resource bisa berubah (pernah menjadi `flowerdecorations*`).
   Selalu pakai `Resource::getUrl()`.
3. Beberapa test lama masih memakai path lama dan merah karena perubahan slug
   di atas (`WeddingAppTest`, `WelcomeStorefrontGuestAccessTest`,
   `WelcomePanelTest`, bagian `WelcomeCatalogDetailParityTest`) — perlu
   migrasi ke `Resource::getUrl()`.

## Cakupan Test per Fitur Baru

| Fitur | File test |
|---|---|
| Tombol kembali Sign-In dinamis | `tests/Feature/SignInBackButtonTest.php` |
| Rename SignIn/SignUp/Home + view social-buttons + tanpa register admin | `tests/Feature/RenameSmokeTest.php` |
| Alur guest Add to Cart anti-419 + cart user login | `tests/Feature/GuestAddToCartTest.php` |
