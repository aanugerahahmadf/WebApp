<?php

namespace Tests\Feature\Filament\KomentarPenilaianKatalogBerandaTest;

use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\MyReviewsResource\MyReviewsResource;
use App\Filament\Welcome\Widgets\CatalogRatingCommentsWidget\CatalogRatingCommentsWidget;
use App\Livewire\Welcome\CatalogRatingCommentsModal\CatalogRatingCommentsModal;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Komentar Penilaian Katalog" bukan halaman: tidak ada route, tidak ada item
 * menu, dan tidak ada kartunya di Beranda. Komentar pengguna lain hanya dibaca
 * lewat dua permukaan -- section "Komentar Penilaian Katalog" di halaman detail
 * katalog dan halaman pribadi "Komentar Penilaian Katalog Saya" (`/my-reviews`).
 *
 * Kelas `CatalogRatingCommentsWidget` dan `CatalogRatingCommentsModal`
 * sengaja TIDAK dihapus, hanya tidak diregistrasikan (`Home::getWidgets()`
 * tidak menyebutnya), jadi tetap diuji di sini dengan menginstansiasinya
 * langsung lewat `Livewire::test`.
 *
 * Menyaring dilakukan lewat chip bintang / dengan-komentar / dengan-media,
 * bukan kolom cari -- sama seperti marketplace besar.
 */
class KomentarPenilaianKatalogBerandaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'customer']);

        Filament::setCurrentPanel(Filament::getPanel('welcome'));

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('customer');

        $this->actingAs($this->user, 'web');
    }

    private function makeReview(User $writer, array $attributes = []): Review
    {
        return Review::factory()->create(array_merge([
            'user_id' => $writer->id,
            'catalog_id' => Catalog::factory(),
            'package_id' => null,
        ], $attributes));
    }

    public function test_catalog_rating_comments_has_no_page_route(): void
    {
        $this->get('/all-user-reviews')->assertNotFound();
        $this->get('/catalog-rating-comments')->assertNotFound();
        $this->get('/my-reviews')->assertSuccessful();

        // Tidak ada apa pun di navigasi yang menunjuk ke halaman ulasan
        // lintas katalog -- halaman itu memang tidak pernah ada.
        $this->assertNotContains(
            url('/all-user-reviews'),
            collect(Filament::getNavigationGroups())
                ->flatMap(fn ($group) => $group->getItems())
                ->map(static fn ($item): string => (string) ($item->getUrl() ?? ''))
                ->all()
        );
    }

    public function test_home_dashboard_does_not_register_the_catalog_rating_comments_card(): void
    {
        // Komentar lintas katalog bukan konsumsi Beranda: like the marketplaces,
        // daftar dan ringkasannya hidup di halaman detail katalog. Kartu dan
        // modalnya tetap ada sebagai kelas (tidak dihapus) tetapi tidak
        // diregistrasikan di Beranda.
        $this->assertNotContains(
            CatalogRatingCommentsWidget::class,
            (new Home())->getWidgets()
        );
    }

    public function test_card_previews_latest_reviews_of_every_user(): void
    {
        $writers = User::factory()->count(12)->create();

        foreach ($writers as $writer) {
            $this->makeReview($writer);
        }

        $widget = Livewire::test(CatalogRatingCommentsWidget::class)
            ->assertOk()
            ->assertSee('Komentar Penilaian Katalog')
            ->assertSee('Lihat semua');

        $this->assertCount(CatalogRatingCommentsWidget::PREVIEW_LIMIT, $widget->instance()->previewRatingComments());
        $this->assertSame(12, $widget->instance()->ratingCommentsSummaryData('x')['total']);
    }

    public function test_modal_is_closed_until_the_card_asks_for_it(): void
    {
        $this->makeReview(User::factory()->create(['full_name' => 'Budi Santoso']), [
            'comment' => 'Dekorasi dari pengguna lain.',
        ]);

        Livewire::test(CatalogRatingCommentsModal::class)
            ->assertOk()
            ->assertDontSee('Dekorasi dari pengguna lain.');

        Livewire::test(CatalogRatingCommentsModal::class)
            ->call('openRatingComments')
            ->assertSee('Budi Santoso')
            ->assertSee('Dekorasi dari pengguna lain.');
    }

    public function test_modal_loads_every_review_without_pagination(): void
    {
        $writers = User::factory()->count(12)->create();

        foreach ($writers as $writer) {
            $this->makeReview($writer);
        }

        $modal = Livewire::test(CatalogRatingCommentsModal::class)->call('openRatingComments');

        $this->assertCount(12, $modal->instance()->ratingCommentsList());
    }

    public function test_modal_masks_only_anonymous_reviewers(): void
    {
        $this->makeReview(User::factory()->create(['full_name' => 'Nama Terbuka']), [
            'is_anonymous' => false,
            'comment' => 'Ulasan terbuka.',
        ]);
        $this->makeReview(User::factory()->create(['full_name' => 'Nama Dirahasiakan']), [
            'is_anonymous' => true,
            'comment' => 'Ulasan anonim.',
        ]);

        Livewire::test(CatalogRatingCommentsModal::class)
            ->call('openRatingComments')
            ->assertSee('Nama Terbuka')
            ->assertSee('Ulasan anonim.')
            ->assertDontSee('Nama Dirahasiakan');
    }

    public function test_modal_filters_with_chips_instead_of_a_search_box(): void
    {
        $this->makeReview(User::factory()->create(), ['comment' => 'Pelaminan mawar putih.']);

        $modal = Livewire::test(CatalogRatingCommentsModal::class)
            ->call('openRatingComments')
            ->assertSee('Pelaminan mawar putih.')
            ->assertSee('Dengan Komentar')
            ->assertSee('Dengan Media');

        $this->assertStringNotContainsString('type="search"', $modal->html());
        $this->assertFalse(property_exists($modal->instance(), 'ratingCommentsSearch'));
    }

    public function test_modal_sort_orders_the_list(): void
    {
        $old = $this->makeReview(User::factory()->create(), ['created_at' => now()->subDays(5)]);
        $new = $this->makeReview(User::factory()->create(), ['created_at' => now()]);

        $modal = Livewire::test(CatalogRatingCommentsModal::class)->call('openRatingComments');

        $this->assertSame(
            [$new->id, $old->id],
            $modal->instance()->ratingCommentsList()->pluck('id')->all()
        );

        $modal->set('ratingCommentsSort', 'oldest');

        $this->assertSame(
            [$old->id, $new->id],
            $modal->instance()->ratingCommentsList()->pluck('id')->all()
        );
    }

    public function test_modal_reports_the_my_reviews_page_still_owns_personal_reviews(): void
    {
        $this->assertSame(url('/my-reviews'), MyReviewsResource::getUrl('index'));
    }
}
