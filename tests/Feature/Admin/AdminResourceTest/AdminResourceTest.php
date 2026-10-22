<?php

namespace Tests\Feature\Admin\AdminResourceTest;

use App\Filament\Admin\Resources\AdminResource\AdminResource;
use App\Filament\Admin\Resources\AdminResource\Pages\ManageAdmins\ManageAdmins;
use App\Models\Admin\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.team@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_can_view_admins_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/admins');

        $response->assertSuccessful();
    }

    public function test_admins_table_renders_successfully(): void
    {
        $admin = Admin::factory()->create([
            'full_name' => 'John Staff Developer',
            'username' => 'johnstaff',
            'email' => 'john.staff@test.local',
        ]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ManageAdmins::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertSee('John Staff Developer');
    }

    public function test_super_admin_can_toggle_admin_active_status(): void
    {
        $subAdmin = Admin::factory()->create([
            'active_status' => true,
        ]);

        $this->assertTrue((bool) $subAdmin->active_status);

        $subAdmin->update(['active_status' => false]);
        $this->assertFalse((bool) $subAdmin->fresh()->active_status);
    }

    public function test_super_admin_cannot_be_deleted(): void
    {
        $canDelete = AdminResource::canDelete($this->superAdmin);
        $this->assertFalse($canDelete);
    }
}
