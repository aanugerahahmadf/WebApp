<?php

namespace Tests\Feature\Admin\VoucherResourceTest;

use App\Filament\Admin\Resources\VoucherResource\Pages\ManageVouchers\ManageVouchers;
use App\Models\Admin\Admin;
use App\Models\Discount\Discount;
use App\Models\Voucher\Voucher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoucherResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.voucher@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_can_view_vouchers_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/vouchers');

        $response->assertSuccessful();
    }

    public function test_vouchers_table_renders_existing_vouchers(): void
    {
        $discount = Discount::factory()->create(['description' => 'Diskon Promo']);
        $voucher = Voucher::factory()->create([
            'name' => 'Voucher Pernikahan Akbar',
            'code' => 'WEDDINGAKBAR',
            'discount_id' => $discount->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ManageVouchers::class)
            ->assertCanSeeTableRecords([$voucher])
            ->assertSee('WEDDINGAKBAR');
    }

    public function test_voucher_can_be_created_via_factory(): void
    {
        $voucher = Voucher::factory()->global()->create([
            'code' => 'GLOBALPROMO2026',
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'code' => 'GLOBALPROMO2026',
            'is_global' => true,
        ]);
    }
}
