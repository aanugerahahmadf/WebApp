<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\Order\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'paid', 'failed', 'refunded']);

        return [
            'order_id' => Order::factory(),
            'method' => fake()->randomElement([
                'bank_transfer', 'qris', 'virtual_account',
                'credit_card', 'gopay', 'ovo', 'dana',
            ]),
            'status' => $status,
            'amount' => fake()->randomFloat(2, 500000, 25000000),
            'paid_at' => $status === 'paid' ? now() : null,
            'transaction_id' => 'PAY-' . strtoupper(Str::random(12)),
            'proof_url' => $status === 'paid' ? 'https://storage.example.com/proofs/' . Str::random(20) . '.jpg' : null,
        ];
    }

    /**
     * Pembayaran lunas.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
            'proof_url' => 'https://storage.example.com/proofs/' . Str::random(20) . '.jpg',
        ]);
    }

    /**
     * Pembayaran pending.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'paid_at' => null,
            'proof_url' => null,
        ]);
    }

    /**
     * Pembayaran gagal.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'paid_at' => null,
            'proof_url' => null,
        ]);
    }

    /**
     * Pembayaran refund.
     */
    public function refunded(): static
    {
        return $this->state(fn () => ['status' => 'refunded']);
    }

    /**
     * Metode pembayaran transfer bank.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn () => ['method' => 'bank_transfer']);
    }

    /**
     * Metode pembayaran QRIS.
     */
    public function qris(): static
    {
        return $this->state(fn () => ['method' => 'qris']);
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
