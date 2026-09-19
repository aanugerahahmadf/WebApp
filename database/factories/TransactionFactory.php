<?php

namespace Database\Factories;

use App\Enums\PaymentStatus\PaymentStatus;
use App\Enums\TransactionType\TransactionType;
use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $type = fake()->randomElement(TransactionType::cases());
        $amount = fake()->randomFloat(2, 50000, 5000000);
        $adminFee = fake()->randomFloat(2, 0, 5000);
        $status = fake()->randomElement(PaymentStatus::cases());

        return [
            'user_id' => User::factory(),
            'order_id' => $type === TransactionType::ORDER ? Order::factory() : null,
            'type' => $type,
            'reference_number' => 'TRX-'.strtoupper(Str::random(12)),
            'amount' => $amount,
            'admin_fee' => $adminFee,
            'total_amount' => $amount + $adminFee,
            'payment_gateway' => 'manual',
            'payment_method' => fake()->randomElement([
                'bank_transfer', 'qris',
            ]),
            'payment_url' => null,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::SUCCESS ? now() : null,
            'notes' => fake()->optional(0.5)->sentence(),
            'metadata' => [
                'customer_name' => fake()->name(),
                'customer_email' => fake()->email(),
            ],
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PENDING,
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
            'paid_at' => null,
            'notes' => fake()->sentence(),
        ]);
    }

    public function topup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TOPUP,
            'order_id' => null,
        ]);
    }

    public function order(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::ORDER,
            'order_id' => Order::factory(),
        ]);
    }
}
