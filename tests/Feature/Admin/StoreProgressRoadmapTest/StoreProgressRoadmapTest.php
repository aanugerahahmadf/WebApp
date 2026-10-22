<?php

namespace Tests\Feature\Admin\StoreProgressRoadmapTest;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Widgets\StoreProgressRoadmap\StoreProgressRoadmap;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\Voucher\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoreProgressRoadmapTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.roadmap@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_admin_dashboard_renders_store_progress_roadmap_widget(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/home');

        $response->assertSuccessful();
        $response->assertSee(__('Roadmap Pengelolaan Toko'));
        $response->assertSee(__('Progress Setup'));
    }

    public function test_store_progress_roadmap_widget_livewire_component(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(StoreProgressRoadmap::class)
            ->assertSee(__('Roadmap Pengelolaan Toko'))
            ->assertSee(__('Produk & Katalog'))
            ->assertSee(__('Kategori Produk'))
            ->assertSee(__('Voucher & Promo'))
            ->assertSee(__('Pesanan Masuk'))
            ->assertSee(__('Pembayaran Lunas'))
            ->assertSee(__('Ulasan Pelanggan'))
            ->assertSee(__('Pendapatan Tercatat'));
    }

    public function test_store_progress_roadmap_reflects_counts(): void
    {
        // Add a category and catalog to check progress reflects
        $category = Category::create([
            'name' => 'Dekorasi Rustic',
            'slug' => 'dekorasi-rustic',
            'is_active' => true,
        ]);

        Catalog::create([
            'category_id' => $category->id,
            'name' => 'Pelaminan Rustic Mewah',
            'slug' => 'pelaminan-rustic-mewah',
            'description' => 'Dekorasi pelaminan rustic cantik',
            'price' => 5000000,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Clear cache so widget calculates fresh values
        \Illuminate\Support\Facades\Cache::forget('store_progress_roadmap_v2');

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(StoreProgressRoadmap::class)
            ->assertSee(__('1 produk'))
            ->assertSee(__('1 kategori'))
            ->assertSee(__('Selesai'));
    }
}
