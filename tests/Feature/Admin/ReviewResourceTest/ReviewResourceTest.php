<?php

namespace Tests\Feature\Admin\ReviewResourceTest;

use App\Filament\Admin\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.review@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_can_view_reviews_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/reviews');

        $response->assertSuccessful();
    }

    public function test_reviews_table_renders_customer_reviews(): void
    {
        $user = User::factory()->create(['full_name' => 'Siti Khadijah']);
        $catalog = Catalog::factory()->create(['name' => 'Dekorasi Mewah']);

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'catalog_id' => $catalog->id,
            'package_id' => null,
            'rating' => 5,
            'comment' => 'Bunga dan pelaminan sangat mewah dan rapi sekali!',
        ]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ManageReviews::class)
            ->assertCanSeeTableRecords([$review])
            ->assertSee('Siti Khadijah');
    }

    public function test_review_rating_validation_and_persistence(): void
    {
        $user = User::factory()->create();
        $catalog = Catalog::factory()->create();

        $review = Review::factory()->fiveStar()->create([
            'user_id' => $user->id,
            'catalog_id' => $catalog->id,
            'package_id' => null,
        ]);

        $this->assertEquals(5, $review->rating);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
        ]);
    }
}
