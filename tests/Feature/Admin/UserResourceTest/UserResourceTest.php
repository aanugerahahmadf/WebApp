<?php

namespace Tests\Feature\Admin\UserResourceTest;

use App\Filament\Admin\Resources\UserResource\Pages\ManageUsers\ManageUsers;
use App\Models\Admin\Admin;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.customers@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_can_view_users_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/users');

        $response->assertSuccessful();
    }

    public function test_users_table_renders_registered_users(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'username' => 'budisantoso99',
        ]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ManageUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->assertSee('Budi Santoso');
    }

    public function test_user_active_status_can_be_updated(): void
    {
        $user = User::factory()->create(['active_status' => true]);

        $this->assertTrue((bool) $user->active_status);

        $user->update(['active_status' => false]);
        $this->assertFalse((bool) $user->fresh()->active_status);
    }
}
