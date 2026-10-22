<?php

namespace Tests\Feature\Admin\OrderResourceTest;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders\ListOrders;
use App\Models\Admin\Admin;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;
    protected User $customer;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.orders@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->customer = User::factory()->create(['full_name' => 'Dewi Lestari']);
        $this->package = Package::factory()->create(['name' => 'Paket Gold Romantic']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_can_view_orders_index_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/orders');

        $response->assertSuccessful();
    }

    public function test_orders_table_lists_existing_orders(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'order_number' => 'ORD-TEST-9988',
            'total_price' => 12500000,
            'status' => OrderStatus::PENDING,
            'payment_status' => OrderPaymentStatus::UNPAID,
        ]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertSee('ORD-TEST-9988');
    }

    public function test_super_admin_can_view_order_detail_page(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'order_number' => 'ORD-DETAIL-7766',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get("/admin/orders/{$order->id}");

        $response->assertSuccessful();
    }

    public function test_order_status_transitions_correctly(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => OrderStatus::PENDING,
            'payment_status' => OrderPaymentStatus::UNPAID,
        ]);

        $order->update([
            'status' => OrderStatus::CONFIRMED,
            'payment_status' => OrderPaymentStatus::PAID,
        ]);

        $fresh = $order->fresh();
        $this->assertEquals(OrderStatus::CONFIRMED, $fresh->status);
        $this->assertEquals(OrderPaymentStatus::PAID, $fresh->payment_status);
    }
}
