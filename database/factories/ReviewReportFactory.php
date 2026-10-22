<?php

namespace Database\Factories;

use App\Models\Review\Review;
use App\Models\Review\ReviewReport;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReport>
 */
class ReviewReportFactory extends Factory
{
    protected $model = ReviewReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = ['spam', 'inappropriate', 'fake_review', 'offensive', 'off_topic', 'other'];
        $descriptions = [
            'spam' => 'Ulasan ini tampak seperti spam atau promosi terselubung dari kompetitor.',
            'inappropriate' => 'Konten ulasan mengandung kata-kata tidak pantas dan menyinggung.',
            'fake_review' => 'Saya yakin ulasan ini palsu karena tidak sesuai dengan fakta layanan yang diberikan.',
            'offensive' => 'Ulasan mengandung serangan pribadi dan konten yang menyinggung SARA.',
            'off_topic' => 'Ulasan tidak relevan dengan produk/layanan dekorasi yang dipesan.',
            'other' => 'Alasan lainnya yang perlu ditinjau oleh tim moderator.',
        ];

        $reason = fake()->randomElement($reasons);
        $status = fake()->randomElement(['pending', 'reviewed', 'dismissed', 'removed']);

        return [
            'review_id' => Review::factory(),
            'reporter_id' => User::factory(),
            'reason' => $reason,
            'description' => $descriptions[$reason] ?? fake()->sentence(),
            'status' => $status,
            'reviewed_by' => in_array($status, ['reviewed', 'dismissed', 'removed']) ? 1 : null,
            'reviewed_at' => in_array($status, ['reviewed', 'dismissed', 'removed']) ? now() : null,
        ];
    }

    /**
     * Status Laporan.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'status' => 'reviewed',
            'reviewed_by' => 1,
            'reviewed_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => 'dismissed',
            'reviewed_by' => 1,
            'reviewed_at' => now(),
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn () => [
            'status' => 'removed',
            'reviewed_by' => 1,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Alasan Laporan Spesifik.
     */
    public function spam(): static
    {
        return $this->state(fn () => [
            'reason' => 'spam',
            'description' => 'Ulasan ini tampak seperti spam promosi terselubung.',
        ]);
    }

    public function fakeReview(): static
    {
        return $this->state(fn () => [
            'reason' => 'fake_review',
            'description' => 'Ulasan palsu yang tidak sesuai dengan fakta layanan.',
        ]);
    }

    /**
     * Untuk ulasan tertentu.
     */
    public function forReview(Review|int $review): static
    {
        $id = $review instanceof Review ? $review->id : $review;
        return $this->state(fn () => ['review_id' => $id]);
    }

    /**
     * Dilaporkan oleh user tertentu.
     */
    public function reportedBy(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['reporter_id' => $id]);
    }
}
