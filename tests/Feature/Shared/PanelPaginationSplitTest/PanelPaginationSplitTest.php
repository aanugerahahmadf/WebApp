<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders\ListOrders;
use App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions\ManageReferenceOptions;
use App\Filament\User\Resources\CartResource\Pages\ManageCarts\ManageCarts;
use App\Filament\User\Resources\HistoryResource\Pages\ListHistories\ListHistories;
use App\Filament\User\Resources\OrderResource\Pages\ManageOrders\ManageOrders;
use App\Filament\User\Resources\PackageResource\Pages\ManagePackages\ManagePackages;
use App\Filament\User\Resources\ProductResource\Pages\ManageProducts\ManageProducts;
use App\Filament\User\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Filament\User\Resources\VoucherResource\Pages\ManageVouchers\ManageVouchers;
use App\Filament\User\Resources\WishlistResource\Pages\ManageWishlists\ManageWishlists;
use App\Support\AppPlatform\AppPlatform;
use App\Models\User\User;
use Database\Factories\HistoryFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
 * The two panels disagree on purpose about pagination, and the disagreement has
 * to be enforced rather than left to whoever adds the next resource.
 *
 * The user panel lists one customer's own data (cart, wishlist, order history,
 * reviews, vouchers), so a paginator only hides rows the user already knows
 * exist: every user resource declares `->paginated(false)` in its own table()
 * and shows the lot. The admin panel lists the whole business, so it keeps
 * pagination via app/Filament/Admin/Concerns/HasMobilePagination.
 *
 * Both declare it from table() rather than by overriding
 * getTableRecordsPerPageSelectOptions(): that method is deprecated and its only
 * caller is InteractsWithTable::table(), which ListRecords::table() replaces
 * with a straight delegation to the Resource, so an override of it is silently
 * dead on exactly these pages. These tests are what keep that from coming back.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user);
});

/**
 * Every ListRecords/ManageRecords page in the user panel.
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
        ManagePackages::class,
        ManageProducts::class,
        ManageReviews::class,
        ManageVouchers::class,
        ManageWishlists::class,
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

test('the admin panel keeps its paginator', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(Livewire::test(ListOrders::class)->instance()->getTable()->isPaginated())->toBeTrue();
});

test('the admin panel offers a single larger page on a phone', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $table = Livewire::test(ListOrders::class)->instance()->getTable();

    // One option, so the per-page dropdown is a control with nothing to choose.
    expect($table->getPaginationPageOptions())->toBe([20])
        ->and($table->getDefaultPaginationPageOption())->toBe(20);
});

test('the admin panel keeps the per-page picker on a desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // ReferenceOptionResource declares ->paginated([10, 25, 50]) itself. The
    // trait must leave that alone on a desktop: page size is a per-table
    // decision about how much of a wide table fits a screen.
    $table = Livewire::test(ManageReferenceOptions::class)->instance()->getTable();

    expect($table->getPaginationPageOptions())->toBe([10, 25, 50])
        ->and($table->getDefaultPaginationPageOption())->toBe(10);
});

test('a phone overrides the page size the resource declared', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $table = Livewire::test(ManageReferenceOptions::class)->instance()->getTable();

    expect($table->isPaginated())->toBeTrue()
        ->and($table->getPaginationPageOptions())->toBe([20])
        ->and($table->getDefaultPaginationPageOption())->toBe(20);
});

test('a desktop admin table keeps the page size its resource chose', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // OrderResource declares ->paginated([5]), the pattern most admin
    // resources use.
    $table = Livewire::test(ListOrders::class)->instance()->getTable();

    expect($table->isPaginated())->toBeTrue()
        ->and($table->getPaginationPageOptions())->toBe([5])
        ->and($table->getDefaultPaginationPageOption())->toBe(5);
});
