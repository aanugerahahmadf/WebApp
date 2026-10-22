<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\Admin\Resources\AdminResource\Pages\ManageAdmins\ManageAdmins;
// ApiKeyResource dihapus: halaman API Key sekarang adalah Page (bukan
// ManageRecords), jadi tidak punya tabel dan tidak termasuk daftar halaman
// ber-pagination.
use App\Filament\Admin\Resources\CatalogResource\Pages\ListCatalogs\ListCatalogs;
use App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ListDatabaseStudio\ListDatabaseStudio;
use App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ListDeveloperStudio\ListDeveloperStudio;
use App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts\ManageDiscounts;
use App\Filament\Admin\Resources\HelpResource\Pages\ManageHelps\ManageHelps;
use App\Filament\Admin\Resources\MaintenanceResource\Pages\ManageMaintenance\ManageMaintenance;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders\ListOrders;
use App\Filament\Admin\Resources\PaymentGatewayResource\Pages\ManagePaymentGateways\ManagePaymentGateways;
use App\Filament\Admin\Resources\PaymentMethodResource\Pages\ManagePaymentMethods\ManagePaymentMethods;
use App\Filament\Admin\Resources\PrivacyPolicyResource\Pages\ManagePrivacyPolicies\ManagePrivacyPolicies;
use App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions\ManageReferenceOptions;
use App\Filament\Admin\Resources\ReportResource\Pages\ManageReports\ManageReports;
use App\Filament\Admin\Resources\ReviewResource\Pages\ManageReviews\ManageReviews as AdminManageReviews;
use App\Filament\Admin\Resources\TermsOfServiceResource\Pages\ManageTermsOfServices\ManageTermsOfServices;
use App\Filament\Admin\Resources\TransactionResource\Pages\ListTransactions\ListTransactions;
use App\Filament\Admin\Resources\UserResource\Pages\ManageUsers\ManageUsers;
use App\Filament\Admin\Resources\VoucherResource\Pages\ManageVouchers\ManageVouchers as AdminManageVouchers;
// WebsiteMobileCustomizerResource dihapus: halaman kustomisasi sekarang
// adalah Page (bukan ManageRecords), jadi tidak punya tabel dan tidak
// termasuk daftar halaman ber-pagination.
use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\Pages\ManageWeddingDecorationPolicies\ManageWeddingDecorationPolicies;
use App\Filament\Admin\Resources\WishlistResource\Pages\ManageWishlists\ManageWishlists as AdminManageWishlists;
use App\Filament\User\Resources\CartResource\Pages\ManageCarts\ManageCarts;
use App\Filament\User\Resources\CatalogResource\Pages\ManageCatalogs\ManageCatalogs;
use App\Filament\User\Resources\HistoryResource\Pages\ListHistories\ListHistories;
use App\Filament\User\Resources\OrderResource\Pages\ManageOrders\ManageOrders;
use App\Filament\User\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Filament\User\Resources\VoucherResource\Pages\ManageVouchers\ManageVouchers;
use App\Filament\User\Resources\WishlistResource\Pages\ManageWishlists\ManageWishlists;
use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Database\Factories\HistoryFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Pagination di kedua panel: TIDAK ADA.
 *
 * Dulu kedua panel berbeda Niagara:
 *
 *   - panel USER  -> `->paginated(false)`: data milik orang itu sendiri,
 *                    jadi paginator cuma menyembunyikan baris yang memang
 *                    sudah dia tahu ada.
 *   - panel ADMIN -> ber-pagination, lengkap dengan pemilih "baris per
 *                    halaman" (dan trait HasMobilePagination yang menyempitkan
 *                    jadi satu opsi di HP).
 *
 * Sekarang keduanya sama: **semua baris ditampilkan, tidak ada paginator**.
 * Permintaan pemilik: "SAYA MAUNYA PAGINATIONNYA DI HILANGKAN!!!"
 *
 * Konsekuensinya:
 *
 *   1. Trait `HasMobilePagination` DIHAPUS -- tugasnya hanya mengatur pilihan
 *      jumlah baris per halaman, dan itu tidak ada lagi.
 *   2. Tiap resource admin mendeklarasikan `->paginated(false)` di
 *      `table()`-nya sendiri. `AdminResource` sebelumnya TIDAK mendeklarasikan
 *      apa pun, jadi Filament memakai bawaan 10 baris per halaman -- itu juga
 *      harus ditulis `->paginated(false)`, kalau tidak tabel itu diam-diam
 *      masih ber-pagination.
 *   3. Tabel Livewire di halaman katalog (`CategoryTable`, `PackageTable`)
 *      juga tanpa pagination, supaya tiga tabel di halaman itu konsisten.
 *
 * Both panels declare it from table() rather than by overriding
 * getTableRecordsPerPageSelectOptions(): that method is deprecated and its only
 * caller is InteractsWithTable::table(), which ListRecords::table() replaces
 * with a straight delegation to the Resource, so an override of it is silently
 * dead on exactly these pages. These tests are what keep that from coming back.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // `super_admin` dibuat di KEDUA guard di sini.
    //
    // User di bawah adalah `App\Models\User\User` (panel user dan welcome),
    // dan `assignRole()` mencari role di guard model itu sendiri -- yaitu
    // `web`. Role `super_admin` versi `web` harus ada juga, selain yang
    // versi `admin` milik tabel `admins`.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user);

    // Admin untuk test-test panel admin di file ini.
    //
    // Hanya `actingAs($this->user)` di atas tidak cukup: panel admin berjalan
    // di guard `admin` (tabel `admins`), jadi `Livewire::test(ListOrders::class)`
    // mount-nya ditolak `canAccess()` dan komponennya tidak pernah ter-mount.
    $this->admin = Admin::factory()->superAdmin()->create([
        'email_verified_at' => now(),
        'active_status'     => true,
    ]);
});

/**
 * Setiap halaman daftar panel user.
 *
 * Enumerated by hand on purpose: a test that discovers the pages by scanning the
 * directory would keep passing if the trait were dropped from a page, which is
 * the exact regression worth catching.
 */
function userListPages(): array
{
    return [
        ManageCarts::class,
        ListHistories::class,
        ManageOrders::class,
        ManageCatalogs::class,
        ManageReviews::class,
        ManageVouchers::class,
        ManageWishlists::class,
    ];
}

/**
 * Setiap halaman daftar panel admin.
 *
 * Ditulis tangan juga, dengan alasan yang sama: kalau daftar ini dipindai
 * dari direktori, tabel admin yang lupa `->paginated(false)` akan lolos
 * tanpa ketahuan.
 */
function adminListPages(): array
{
    return [
        ManageAdmins::class,
        // (dihapus: ApiKey bukan list page lagi)
        ListCatalogs::class,
        ListDatabaseStudio::class,
        ListDeveloperStudio::class,
        ManageDiscounts::class,
        ManageHelps::class,
        ManageMaintenance::class,
        ListOrders::class,
        ManagePaymentGateways::class,
        ManagePaymentMethods::class,
        ManagePrivacyPolicies::class,
        ManageReferenceOptions::class,
        ManageReports::class,
        AdminManageReviews::class,
        ManageTermsOfServices::class,
        ListTransactions::class,
        ManageUsers::class,
        AdminManageVouchers::class,
        // (dihapus: WebsiteMobileCustomizer bukan list page lagi)
        ManageWeddingDecorationPolicies::class,
        AdminManageWishlists::class,
    ];
}

test('every user panel list page renders all records instead of paginating', function (string $page): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('user'));

    expect(Livewire::test($page)->instance()->getTable()->isPaginated())->toBeFalse();
})->with(userListPages());

test('the user panel drops pagination on a phone too', function (string $page): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('user'));

    expect(Livewire::test($page)->instance()->getTable()->isPaginated())->toBeFalse();
})->with(userListPages());

test('the user panel shows more rows than Filament default page size', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('user'));

    // Past Filament's default page size of 10, so a paginated table would be
    // caught here rather than looking like a pass.
    //
    // History does not use HasFactory, so the factory is instantiated directly.
    HistoryFactory::new()->count(25)->create(['user_id' => $this->user->id]);

    $records = Livewire::test(ListHistories::class)->instance()->getTableRecords();

    expect($records)->toHaveCount(25);
});

test('no user panel page renders a paginator control', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('user'));

    HistoryFactory::new()->count(25)->create(['user_id' => $this->user->id]);

    Livewire::test(ListHistories::class)
        ->assertOk()
        ->assertDontSee('fi-ta-pagination', escape: false);
});

test('every admin panel list page renders all records instead of paginating', function (string $page): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs($this->admin, 'admin');

    expect(Livewire::test($page)->instance()->getTable()->isPaginated())->toBeFalse();
})->with(adminListPages());

test('the admin panel drops pagination on a phone too', function (string $page): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs($this->admin, 'admin');

    expect(Livewire::test($page)->instance()->getTable()->isPaginated())->toBeFalse();
})->with(adminListPages());

test('the admin panel shows more rows than Filament default page size', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs($this->admin, 'admin');

    // 25 baris -- jauh lebih dari bawaan Filament 10 baris per halaman, jadi
    // tabel yang diam-diam masih ber-pagination akan ketahuan di sini.
    //
    // Ditambah 1 user dari `beforeEach()` (user-nya yang berperan sebagai
    // admin shopper), jadi totalnya 26.
    User::factory()->count(25)->create();

    $records = Livewire::test(ManageUsers::class)->instance()->getTableRecords();

    expect($records)->toHaveCount(26);
});

test('no admin panel page renders a paginator control', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs($this->admin, 'admin');

    User::factory()->count(25)->create();

    Livewire::test(ManageUsers::class)
        ->assertOk()
        ->assertDontSee('fi-pagination', escape: false);
});
