<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $status = fake()->randomElement(OrderStatus::cases());

        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'product_id' => null,
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'total_price' => fake()->randomFloat(2, 500000, 10000000),
            'status' => $status,
            'payment_status' => match ($status) {
                OrderStatus::PENDING => OrderPaymentStatus::UNPAID,
                OrderStatus::CONFIRMED => OrderPaymentStatus::PAID,
                OrderStatus::COMPLETED => OrderPaymentStatus::PAID,
                OrderStatus::CANCELLED => OrderPaymentStatus::CANCELLED,
                default => fake()->randomElement(OrderPaymentStatus::cases()),
            },
            'booking_date' => Carbon::now()->addDays(fake()->numberBetween(7, 90)),
            'booking_time' => fake()->randomElement(['08:00', '09:00', '10:00', '13:00', '15:00']),
            'quantity' => fake()->numberBetween(1, 3),
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PENDING,
            'payment_status' => OrderPaymentStatus::UNPAID,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CONFIRMED,
            'payment_status' => OrderPaymentStatus::PAID,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::COMPLETED,
            'payment_status' => OrderPaymentStatus::PAID,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
            'payment_status' => OrderPaymentStatus::CANCELLED,
        ]);
    }
}
