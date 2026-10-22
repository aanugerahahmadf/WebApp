<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Models\Catalog\Catalog;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(OrderStatus::cases());

        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'catalog_id' => null,
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'total_price' => fake()->randomFloat(2, 2500000, 25000000),
            'status' => $status,
            'payment_status' => match ($status) {
                OrderStatus::PENDING => OrderPaymentStatus::UNPAID,
                OrderStatus::CONFIRMED => fake()->randomElement([OrderPaymentStatus::PARTIAL, OrderPaymentStatus::PAID]),
                OrderStatus::PREPARING => OrderPaymentStatus::PAID,
                OrderStatus::EVENT_DAY => OrderPaymentStatus::PAID,
                OrderStatus::COMPLETED => OrderPaymentStatus::PAID,
                OrderStatus::CANCELLED => fake()->randomElement([OrderPaymentStatus::CANCELLED, OrderPaymentStatus::REFUNDED]),
            },
            'booking_date' => Carbon::now()->addDays(fake()->numberBetween(7, 90)),
            'booking_time' => fake()->randomElement(['08:00', '09:00', '10:00', '13:00', '15:00', '18:00']),
            'quantity' => fake()->numberBetween(1, 3),
            'notes' => fake()->optional(0.7)->sentence(10),
        ];
    }

    /**
     * Pesanan untuk Paket Pernikahan.
     */
    public function forPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'package_id' => $package instanceof Package ? $package->id : ($package ?? Package::factory()),
            'catalog_id' => null,
        ]);
    }

    /**
     * Pesanan untuk Katalog Bunga.
     */
    public function forCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => $catalog instanceof Catalog ? $catalog->id : ($catalog ?? Catalog::factory()),
            'package_id' => null,
        ]);
    }

    /**
     * States Status Pesanan (OrderStatus).
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PENDING,
            'payment_status' => OrderPaymentStatus::UNPAID,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::CONFIRMED,
            'payment_status' => OrderPaymentStatus::PAID,
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PREPARING,
            'payment_status' => OrderPaymentStatus::PAID,
        ]);
    }

    public function eventDay(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::EVENT_DAY,
            'payment_status' => OrderPaymentStatus::PAID,
            'booking_date' => Carbon::today(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::COMPLETED,
            'payment_status' => OrderPaymentStatus::PAID,
            'booking_date' => Carbon::now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::CANCELLED,
            'payment_status' => OrderPaymentStatus::CANCELLED,
        ]);
    }

    /**
     * States Status Pembayaran (OrderPaymentStatus).
     */
    public function unpaid(): static
    {
        return $this->state(fn () => [
            'payment_status' => OrderPaymentStatus::UNPAID,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => OrderPaymentStatus::PAID,
        ]);
    }

    public function partialPaid(): static
    {
        return $this->state(fn () => [
            'payment_status' => OrderPaymentStatus::PARTIAL,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'payment_status' => OrderPaymentStatus::REFUNDED,
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    /**
     * Waktu Pelaksanaan Acara.
     */
    public function today(): static
    {
        return $this->state(fn () => [
            'booking_date' => Carbon::today(),
        ]);
    }

    public function upcoming(int $days = 14): static
    {
        return $this->state(fn () => [
            'booking_date' => Carbon::now()->addDays($days),
        ]);
    }

    public function past(int $days = 30): static
    {
        return $this->state(fn () => [
            'booking_date' => Carbon::now()->subDays($days),
        ]);
    }

    /**
     * Pesanan Bernilai Tinggi (Luxury).
     */
    public function highValue(): static
    {
        return $this->state(fn () => [
            'total_price' => fake()->randomFloat(2, 25000000, 75000000),
            'quantity' => 1,
            'notes' => 'Pesanan Royal VIP Grand Ballroom dengan bunga impor premium.',
        ]);
    }
}
