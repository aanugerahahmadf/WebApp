<?php

namespace Database\Factories;

use App\Models\Review\Review;
use App\Models\ReviewReply\ReviewReply;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReply>
 */
class ReviewReplyFactory extends Factory
{
    protected $model = ReviewReply::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $replies = [
            'Terima kasih atas ulasan positifnya! Kami sangat senang acara Anda berjalan lancar dan dekorasi sesuai harapan. Semoga bahagia selalu! 🌸',
            'Terima kasih atas masukan yang membangun! Kami akan terus meningkatkan standar pelayanan dan ketepatan waktu tim kami. Mohon maaf atas ketidaknyamanannya.',
            'Ulasan yang sangat berharga bagi kami! Tim desainer kami sangat bangga dengan hasilnya. Semoga pernikahan Anda penuh berkah dan kebahagiaan!',
            'Kami meminta maaf atas kekurangan yang terjadi. Tim manajemen sudah mendapat laporan dan sedang melakukan evaluasi internal agar tidak terulang.',
            'Wow, terima kasih banyak! Ulasan Anda sangat memotivasi tim kami untuk terus berkarya. Jangan lupa rekomendasi ke teman-teman ya! 💐',
            'Senang sekali mendengar Anda puas dengan hasil dekorasi! Jika ada yang ingin dikonsultasikan di kemudian hari, jangan ragu untuk menghubungi kami.',
            'Terima kasih telah mempercayakan momen spesial Anda kepada kami. Feedback Anda sangat berarti dan kami akan jadikan bahan evaluasi tim.',
        ];

        return [
            'review_id' => Review::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->randomElement($replies),
        ];
    }

    /**
     * Balasan resmi dari pihak toko (admin/CS).
     */
    public function fromAdmin(): static
    {
        return $this->state(fn () => [
            'comment' => 'Terima kasih atas ulasan Anda! Tim kami sangat menghargai feedback yang diberikan. Salam hangat dari kami untuk pasangan baru! 🌺',
        ]);
    }

    /**
     * Balasan dari pengguna lain (komunitas).
     */
    public function fromUser(): static
    {
        return $this->state(fn () => [
            'comment' => 'Setuju! Saya juga pernah menggunakan layanan mereka dan sangat puas dengan hasilnya!',
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
     * Oleh user tertentu.
     */
    public function byUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }
}
