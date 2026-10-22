<?php

/**
 * Tests for the "Chat Admin" button and "Add to Favorites" button
 * on the Welcome panel catalog detail page.
 *
 * Covers:
 * - chat_admin works for a signed-in customer (redirects to messages)
 * - chat_admin works for a guest (GuestIdentity path)
 * - chat_admin shows an error notification when no admin exists
 * - wishlist_detail adds to wishlist for a signed-in customer
 * - wishlist_detail removes from wishlist when already saved
 * - wishlist_detail redirects guest to login
 * - No 419 / CSRF error on wishlist_detail (no modal form)
 */

use App\Filament\Welcome\Pages\MessagesPage\MessagesPage;
use App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Admin\Admin;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Catalog\Catalog;
use App\Models\User\User;
use App\Models\Wishlist\Wishlist;
use App\Services\GuestIdentity\GuestIdentity;
use App\Support\ChatStaff\ChatStaff;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    // `super_admin` dibuat DUA KALI dengan guard berbeda, dan itu bukan duplikasi
    // yang sia-sia.
    //
    // Role admin yang asli hidup di guard `admin` (tabel `admins`), sedangkan
    // `Admin::factory()->superAdmin()` mencari role di guard model -- jadi
    // `firstOrCreate(['name' => 'super_admin'])` tanpa guard akan membuat role
    // ber-guard `web`, dan pemanggilannya melempar `RoleDoesNotExist`.
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    // Admin untuk chat harus punya IDENTITAS BELANJA tertaut di `admins.user_id`.
    //
    // `ChatStaff::adminUser()` mencari `Admin::query()->whereNotNull('user_id')`
    // -- bukan role di tabel `users`. Jadi `User::factory()->create()` +
    // `assignRole('super_admin')` seperti sebelumnya tidak cukup: tabelnya
    // sudah dipisah, dan role `super_admin` yang ber-guard `admin` tidak akan
    // pernah muncul sebagai Super Admin di panel user.
    //
    // Gejalanya kalau ini salah: `getOrCreateInboxWithAdmin()` mengembalikan
    // null, action berhenti dengan notifikasi "Fitur chat belum tersedia",
    // dan TIDAK ADA redirect -- persis yang dilaporkan test ini dengan
    // pesan "Component did not perform a redirect" yang tidak menyiratkan
    // penyebabnya sama sekali.
    $this->admin = Admin::factory()->superAdmin()->create([
        'active_status' => true,
        'user_id'       => User::factory()->create()->id,
    ]);

    // `name_translations`, BUKAN `name`.
    //
    // `Catalog::getNameAttribute()` membaca `trans('name')` dulu dan baru jatuh
    // ke kolom `name` kalau translations-nya kosong. Jadi
    // `create(['name' => 'X'])` tidak menghasilkan nama `X` -- yang tampil tetap
    // nama dari daftar produk di factory, tanpa error, dan gejalanya cuma
    // assertion yang gagal jauh dari penyebabnya. Lihat catatan di
    // `CatalogFactory`.
    $this->catalog = Catalog::factory()->create([
        'stock'              => 5,
        'name_translations'  => [
            'id' => 'Bunga Mawar Test',
            'en' => 'Red Rose Bouquet Test',
        ],
    ]);

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// chat_admin â€” Catalog
// ---------------------------------------------------------------------------

it('chat_admin on a catalog page redirects a signed-in customer to the messages inbox', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    // An inbox must have been created between the customer and the admin
    $this->assertDatabaseHas('fm_inboxes', []);
    $inbox = Inbox::latest()->first();
    expect($inbox->user_ids)->toContain($this->customer->id)
        ->and($inbox->user_ids)->toContain($this->admin->id);

    // A context message must have been written
    $this->assertDatabaseHas('fm_messages', [
        'inbox_id' => $inbox->id,
        'user_id'  => $this->customer->id,
    ]);
});

/*
 * Test "chat_admin on a package page ..." dan "chat_admin sends the package
 * context card ..." DIHAPUS.
 *
 * Keduanya memanggil `ViewPackage::class`, yang sudah tidak ada: kelas itu
 * tinggal di `PackageResource\Pages\ViewPackage`, dan `PackageResource` sendiri
 * dihapus saat penataan ulang `Product` -> `Catalog`. Gejalanya
 * `Unable to find component: [ViewPackage]` -- yang tidak menyiratkan apa pun
 * soal refactor di belakang.
 *
 * Yang hilang bukan cakupan: setiap aturan yang di sini diuji dua kali (sekali
 * untuk katalog, sekali untuk paket) sekarang diuji sekali dengan record
 * `Catalog`. Test `chat_admin sends the catalog context card ...` masih mengunci
 * meta message, termasuk `type` dan `id`-nya.
 *
 * Yang hilang memang adalah pengaman untuk type `package`. Kalau halaman
 * detail paket dihidupkan lagi, `meta.type` untuk paket perlu diuji lagi
 * dengan record yang benar-benar bisa di-resolve oleh ViewCatalog.
 */
it('chat_admin sends the catalog context card as a meta message', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    // Pesan diambil dari INBOX customer ini, bukan dari `latest()` global.
    //
    // `sendContextMessage()` mengembalikan pesan terakhir apa adanya kalau itu
    // sudah context card dengan `id` yang sama (pengaman duplikat). Efeknya: bila
    // inbox sudah berisi context card lain, pemanggilan berikutnya tidak
    // membuat baris baru -- jadi `messages()->latest()->first()` bisa
    // mengembalikan pesan milik katalog lain.
    //
    // `Inbox::latest()->first()` lebih buruk lagi: itu milik admin, bukan
    // customer, dan `user_ids`-nya bisa berisi beberapa pasang karena
    // `getOrCreateInboxWithAdmin()` mencari berdasarkan dua id sekaligus.
    $inbox = Inbox::query()
        ->whereJsonContains('user_ids', $this->customer->id)
        ->latest('id')
        ->first();

    $message = $inbox?->messages()->latest('id')->first();

    expect($message)->not->toBeNull()
        ->and($message->meta['type'])->toBe('catalog')
        ->and($message->meta['id'])->toBe($this->catalog->id)
        ->and($message->meta['name'])->toBe('Bunga Mawar Test');
});

/*
 * Catatan soal cara mencari pesan di test di atas.
 *
 * `Message::whereJsonContains('meta->id', $id)` terlihat lebih tepat dari
 * `latest()`, tapi tidak: `RefreshDatabase` memberi id berurutan mulai dari 1
 * dan seeder sudah mengisi `catalogs`, jadi id katalog dari factory bisa
 * BERHIMPIT dengan baris seed. `meta.id` yang dicek akan cocok ke dua baris,
 * dan `latest('id')` mengambil yang salah.
 *
 * Yang tidak susceptible adalah memfilter lewat inbox milik customer itu:
 * `getOrCreateInboxWithAdmin()` menyimpan pasangan id di `user_ids`, jadi
 * `whereJsonContains('user_ids', $this->customer->id)` tidak bisa ikut membawa
 * inbox customer lain, dan hanya pemanggilan di test ini yang menulis ke sana.
 */
it('chat_admin shows a warning when no admin has a storefront identity yet', function (): void {
    // Kondisi yang diuji adalah "admin belum di-grant", BUKAN "admin tidak ada".
    //
    // `ChatStaff::adminUser()` mencari `Admin::whereNotNull('user_id')` -- jadi
    // baris admin yang ADA tapi `user_id`-nya masih null juga tidak dihitung. Itu
    // keadaan default di setiap instalasi sebelum `php artisan
    // admin:grant-storefront` dijalankan, jadi justru kondisi yang paling
    // sering terjadi di produksi.
    //
    // Test lama menghapus baris admin-nya (`$this->admin->delete()`), yang
    // tidak menguji jalur ini sama sekali: tidak ada admin != admin tanpa
    // identitas. Selain itu, `ChatStaff` men-cache hasilnya, jadi menghapus
    // baris setelah `beforeEach` tidak selalu invalidate cache itu.
    $this->admin->forceFill(['user_id' => null])->save();
    ChatStaff::forget();

    actingAs($this->customer, 'web');

    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        // Tidak ada redirect: action berhenti dengan notifikasi.
        ->assertNotified();

    // Dan tidak ada inbox yang dibuat untuk percakapan yang tidak pernah
    // dimulai -- ini yang mencegah pesan hilang begitu saja.
    expect(Inbox::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// chat_admin â€” Guest (GuestIdentity path)
// ---------------------------------------------------------------------------

it('chat_admin works for a guest via GuestIdentity', function (): void {
    // No actingAs â€” purely anonymous request
    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    // A guest user row should have been created
    $guestUser = app(GuestIdentity::class)->user();
    expect($guestUser)->not->toBeNull();

    // An inbox must exist
    $inbox = Inbox::latest()->first();
    expect($inbox)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// wishlist_detail â€” Catalog
// ---------------------------------------------------------------------------

it('wishlist_detail adds the catalog to the wishlist for a signed-in customer', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseHas('wishlists', [
        'user_id'    => $this->customer->id,
        'catalog_id' => $this->catalog->id,
    ]);
});

it('wishlist_detail removes the catalog from the wishlist when already saved', function (): void {
    actingAs($this->customer, 'web');

    // Pre-insert the wishlist row
    Wishlist::create([
        'user_id'    => $this->customer->id,
        'catalog_id' => $this->catalog->id,
    ]);

    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseMissing('wishlists', [
        'user_id'    => $this->customer->id,
        'catalog_id' => $this->catalog->id,
    ]);
});

/*
 * Bagian "wishlist_detail -- Package" DIHAPUS (tambah + hapus dari wishlist).
 *
 * Keduanya memanggil `ViewPackage::class` yang sudah tidak ada, dan setiap
 * aturan yang mereka uji sudah punya pasangan katalog di atas dan di bawah.
 * Satu-satunya yang hilang adalah pengaman untuk kolom `wishlists.package_id`.
 *
 * Kalau halaman detail paket dihidupkan lagi, dua test ini perlu dikembalikan
 * dengan record `Package` yang benar-benar bisa di-resolve ViewCatalog.
 */

// ---------------------------------------------------------------------------
// wishlist_detail -- Guest redirect (no 419)
// ---------------------------------------------------------------------------

it('wishlist_detail redirects a guest to the auth landing page without throwing 419', function (): void {
    // No actingAs â€” guest session. Tamu mendarat di /auth (Sign In +
    // Continue With Google), bukan form /signin.
    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail')
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));

    // Wishlist row must NOT have been created
    $this->assertDatabaseMissing('wishlists', ['catalog_id' => $this->catalog->id]);
});

// ---------------------------------------------------------------------------
// Idempotency -- double-click protection
// ---------------------------------------------------------------------------

it('double wishlist add only creates one row', function (): void {
    actingAs($this->customer, 'web');

    $component = Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()]);
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');
    // Toggle back off then on again
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    expect(Wishlist::where('user_id', $this->customer->id)
        ->where('catalog_id', $this->catalog->id)
        ->count())->toBe(1);
});

