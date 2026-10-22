<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\Order\OrderTimeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTimeline>
 */
class OrderTimelineFactory extends Factory
{
    protected $model = OrderTimeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statusDescriptions = [
            'pending' => [
                'status' => 'pending',
                'description' => 'Pesanan diterima dan sedang menunggu konfirmasi pembayaran dari pelanggan.',
            ],
            'confirmed' => [
                'status' => 'confirmed',
                'description' => 'Pembayaran dikonfirmasi. Tim kami akan mulai mempersiapkan dekorasi sesuai pesanan.',
            ],
            'preparing' => [
                'status' => 'preparing',
                'description' => 'Tim dekorator sedang menyiapkan seluruh material dan rangkaian bunga untuk acara Anda.',
            ],
            'event_day' => [
                'status' => 'event_day',
                'description' => 'Hari H! Tim sedang melakukan instalasi dekorasi di venue. Selamat melangsungkan pernikahan!',
            ],
            'completed' => [
                'status' => 'completed',
                'description' => 'Acara selesai dengan sukses. Terima kasih telah mempercayakan dekorasi kepada kami.',
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'description' => 'Pesanan dibatalkan sesuai permintaan pelanggan.',
            ],
        ];

        $entry = fake()->randomElement($statusDescriptions);

        return [
            'order_id' => Order::factory(),
            'status' => $entry['status'],
            'description' => $entry['description'],
            'metadata' => [
                'updated_by_role' => fake()->randomElement(['admin', 'super_admin', 'system']),
                'notes' => fake()->optional(0.4)->sentence(),
            ],
            'created_by' => null,
        ];
    }

    /**
     * Timeline pesanan diterima (pending).
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'description' => 'Pesanan baru diterima dan menunggu konfirmasi pembayaran.',
        ]);
    }

    /**
     * Timeline pesanan dikonfirmasi.
     */
    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'description' => 'Pembayaran dikonfirmasi. Dekorasi mulai disiapkan.',
        ]);
    }

    /**
     * Timeline sedang persiapan.
     */
    public function preparing(): static
    {
        return $this->state(fn () => [
            'status' => 'preparing',
            'description' => 'Tim sedang mempersiapkan bunga dan material dekorasi.',
        ]);
    }

    /**
     * Timeline hari pelaksanaan.
     */
    public function eventDay(): static
    {
        return $this->state(fn () => [
            'status' => 'event_day',
            'description' => 'Tim sedang instalasi dekorasi di venue acara.',
        ]);
    }

    /**
     * Timeline selesai.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'description' => 'Acara berlangsung sukses. Dekorasi dibongkar pasang dengan rapi.',
        ]);
    }

    /**
     * Timeline dibatalkan.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'description' => 'Pesanan dibatalkan.',
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
}
