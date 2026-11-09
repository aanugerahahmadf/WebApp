<?php

namespace Tests\Feature\Api\ReviewApiTest;

use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrak API untuk dua bentuk ulasan:
 *
 *   - "Komentar Penilaian Katalog" (kartu + modal Beranda) -> GET /api/reviews (+ /api/reviews/summary)
 *   - "Komentar Penilaian Katalog Saya"                    -> GET /api/reviews/user
 *
 * Keduanya membaca model Review yang sama; tidak ada tabel atau model
 * duplikat untuk masing-masing bentuk.
 */
class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function reviewFor(User $user, array $attributes = []): Review
    {
        return Review::factory()->create(array_merge([
            'user_id' => $user->id,
            'catalog_id' => Catalog::factory(),
            'package_id' => null,
        ], $attributes));
    }

    public function test_guest_cannot_read_reviews(): void
    {
        $this->getJson('/api/reviews')->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_all_reviews_with_summary_and_pagination(): void
    {
        $viewer = User::factory()->create();
        $writer = User::factory()->create(['full_name' => 'Siti Khadijah']);

        $this->reviewFor($writer, [
            'rating' => 5,
            'comment' => 'Dekorasi sangat mewah dan rapi.',
            'photos' => ['review-photos/satu.jpg'],
            'helpful_count' => 7,
        ]);
        $this->reviewFor($writer, [
            'rating' => 3,
            'comment' => 'Cukup baik.',
            'photos' => null,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?per_page=1');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.average', 4)
            ->assertJsonPath('summary.with_photo_count', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.has_more_pages', true)
            ->assertJsonCount(1, 'data');

        $this->assertSame(
            ['star' => 5, 'count' => 1, 'percentage' => 50],
            $response->json('summary.distribution.0')
        );
    }

    public function test_all_reviews_can_be_filtered_and_sorted(): void
    {
        $viewer = User::factory()->create();
        $writer = User::factory()->create();

        $fiveStar = $this->reviewFor($writer, [
            'rating' => 5,
            'comment' => 'Sempurna',
            'photos' => ['review-photos/a.jpg'],
            'helpful_count' => 2,
        ]);
        $threeStar = $this->reviewFor($writer, [
            'rating' => 3,
            'comment' => '',
            'helpful_count' => 20,
            'photos' => null,
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?rating=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fiveStar->id)
            // Summary tetap mengikuti konteks, bukan filter rating.
            ->assertJsonPath('summary.total', 2);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?with_media=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fiveStar->id);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?with_comment=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fiveStar->id);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?sort=most_helpful')
            ->assertOk()
            ->assertJsonPath('data.0.id', $threeStar->id);
    }

    public function test_all_reviews_can_search_by_product_and_comment(): void
    {
        $viewer = User::factory()->create();
        $writer = User::factory()->create();

        $catalog = Catalog::factory()->create(['name' => 'Dekorasi Pelaminan Royal']);
        $match = $this->reviewFor($writer, [
            'catalog_id' => $catalog->id,
            'comment' => 'Pelaminan royal sangat megah.',
        ]);
        $this->reviewFor($writer, ['comment' => 'Buket biasa saja.', 'photos' => null]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews?search=Royal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('summary.total', 1);
    }

    public function test_anonymous_reviews_do_not_leak_the_reviewer_identity(): void
    {
        $viewer = User::factory()->create();
        $writer = User::factory()->create(['full_name' => 'Rahasia Sekali']);

        $anonymous = $this->reviewFor($writer, [
            'is_anonymous' => true,
            'comment' => 'Saya ingin privasi.',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $anonymous->id)
            ->assertJsonPath('data.0.user', null)
            ->assertJsonPath('data.0.user_id', null)
            ->assertJsonPath('data.0.reviewer_avatar', null)
            ->assertJsonPath('data.0.is_anonymous', true);

        $this->assertNotSame('Rahasia Sekali', $response->json('data.0.reviewer_name'));
        $this->assertStringContainsString('*', $response->json('data.0.reviewer_name'));
    }

    public function test_my_reviews_only_returns_own_reviews_with_real_name(): void
    {
        $owner = User::factory()->create(['full_name' => 'Pemilik Ulasan']);
        $other = User::factory()->create(['full_name' => 'Pengguna Lain']);

        $own = $this->reviewFor($owner, [
            'is_anonymous' => true,
            'comment' => 'Ulasan saya sendiri.',
        ]);
        $this->reviewFor($other, ['comment' => 'Ulasan orang lain.']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/reviews/user');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.reviewer_name', 'Pemilik Ulasan')
            ->assertJsonPath('data.0.is_anonymous', true)
            ->assertJsonPath('summary.total', 1);
    }

    public function test_reviews_summary_endpoint_matches_list_summary(): void
    {
        $viewer = User::factory()->create();
        $writer = User::factory()->create();

        $this->reviewFor($writer, ['rating' => 4, 'photos' => null]);
        $this->reviewFor($writer, ['rating' => 4, 'photos' => ['review-photos/x.jpg']]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/reviews/summary')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.average', 4)
            ->assertJsonPath('data.with_media_count', 1)
            ->assertJsonPath('data.distribution.0.star', 5)
            ->assertJsonPath('data.distribution.0.count', 0);
    }
}
