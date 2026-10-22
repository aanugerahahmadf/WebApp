<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\Order\RefundRequest;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundRequest>
 */
class RefundRequestFactory extends Factory
{
    protected $model = RefundRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = [
            'Perubahan jadwal acara mendadak karena kondisi keluarga',
            'Venue pernikahan tidak tersedia pada tanggal yang dipesan',
            'Dekorasi tidak sesuai dengan yang diperjanjikan dalam kontrak',
            'Tim dekorator tidak hadir tepat waktu dan acara terganggu',
            'Kerusakan pada beberapa elemen dekorasi saat pelaksanaan',
            'Pengantin memutuskan menunda pernikahan',
            'Force majeure: bencana alam / situasi darurat',
        ];

        $status = fake()->randomElement(['pending', 'approved', 'rejected', 'processed']);

        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->randomElement($reasons),
            'amount' => fake()->randomFloat(2, 500000, 15000000),
            'evidence_photos' => fake()->boolean(60) ? [
                'https://storage.example.com/evidence/' . fake()->uuid() . '.jpg',
                'https://storage.example.com/evidence/' . fake()->uuid() . '.jpg',
            ] : null,
            'status' => $status,
        ];
    }

    /**
     * Status Pengajuan Refund.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function processed(): static
    {
        return $this->state(fn () => ['status' => 'processed']);
    }

    /**
     * Sertakan bukti foto.
     */
    public function withEvidence(): static
    {
        return $this->state(fn () => [
            'evidence_photos' => [
                'https://storage.example.com/evidence/' . fake()->uuid() . '.jpg',
                'https://storage.example.com/evidence/' . fake()->uuid() . '.jpg',
                'https://storage.example.com/evidence/' . fake()->uuid() . '.jpg',
            ],
        ]);
    }

    /**
     * Untuk pesanan tertentu.
     */
    public function forOrder(Order|int $order): static
    {
        $id = $order instanceof Order ? $order->id : $order;
        return $this->state(fn () => ['order_id' => $id]);
    }

    /**
     * Untuk user tertentu.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }
}
