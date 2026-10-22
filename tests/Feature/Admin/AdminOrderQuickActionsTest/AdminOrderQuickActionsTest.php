<?php

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders\ListOrders;
use App\Models\Admin\Admin;
use App\Models\Order\Order;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

$admin = null;

/**
 * Admin memakai model `Admin` (tabel `admins`, guard `admin`).
 *
 * Dulu test ini membuat `User` lalu menempelkan role `super_admin`. Setelah
 * admin dipisah ke tabel dan guard sendiri, itu tidak lagi cukup: `Role`
 * `super_admin` yang dibuat di sini akan ber-guard 'web', sedangkan panel admin
 * + `AdminAccess` membaca guard 'admin'. Testnya masih hijau untuk action yang
 * hanya memanggil `Livewire::test()` (middleware panel tidak dilewati), tapi
 * gagal begitu mulai memeriksa state -- gejalanya jauh lebih membingungkan
 * daripada errornya.
 *
 * `$buyer` tetap `User`: itu pemilik order-nya, dan memang harus berasal dari
 * tabel `users`.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->buyer = User::factory()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function makeOrder(User $owner, array $overrides = []): Order
{
    return Order::create(array_merge([
        'user_id' => $owner->id,
        'order_number' => 'TEST-'.uniqid(),
        'total_price' => 1500000,
        'status' => OrderStatus::PENDING,
        'payment_status' => OrderPaymentStatus::UNPAID,
        'booking_date' => '2026-12-01',
    ], $overrides));
}

it('renders the order list for a super admin', function () {
    makeOrder($this->buyer);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->assertSuccessful();
});

it('marks an unpaid order as paid via the table action', function () {
    $order = makeOrder($this->buyer);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->callTableAction('mark_paid', $order);

    $order->refresh();
    expect($order->payment_status)->toBe(OrderPaymentStatus::PAID);
    expect($order->status)->toBe(OrderStatus::CONFIRMED);
});

it('completes a confirmed order via the table action', function () {
    $order = makeOrder($this->buyer, [
        'status' => OrderStatus::CONFIRMED,
        'payment_status' => OrderPaymentStatus::PAID,
    ]);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->callTableAction('complete', $order);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::COMPLETED);
});

it('marks an unpaid order as failed via the table action', function () {
    $order = makeOrder($this->buyer);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->callTableAction('mark_failed', $order);

    $order->refresh();
    expect($order->payment_status)->toBe(OrderPaymentStatus::FAILED);
});

it('returns a failed order to pending via the table action', function () {
    $order = makeOrder($this->buyer, [
        'payment_status' => OrderPaymentStatus::FAILED,
    ]);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->callTableAction('mark_pending', $order);

    $order->refresh();
    expect($order->payment_status)->toBe(OrderPaymentStatus::PENDING);
});

it('hides the mark-pending action unless the order payment failed', function () {
    $order = makeOrder($this->buyer);

    actingAs($this->admin, 'admin');

    Livewire::test(ListOrders::class)
        ->assertTableActionHidden('mark_pending', $order);

    expect(Order::find($order->id)->payment_status)->toBe(OrderPaymentStatus::UNPAID);
});