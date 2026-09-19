<?php

namespace Database\Factories;

use App\Enums\DiscountType\DiscountType;
use App\Models\Voucher\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $discountType = fake()->randomElement(DiscountType::cases());

        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'discount_amount' => $discountType === DiscountType::FIXED
                ? fake()->randomFloat(2, 10000, 200000)
                : fake()->numberBetween(5, 50),
            'discount_type' => $discountType,
            'min_purchase' => fake()->randomFloat(2, 0, 500000),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'is_active' => true,
            'is_global' => fake()->boolean(70),
            'max_uses' => fake()->optional(0.6)->numberBetween(10, 100),
            'uses_count' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::FIXED,
            'discount_amount' => fake()->randomFloat(2, 10000, 200000),
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_amount' => fake()->numberBetween(5, 50),
        ]);
    }
}
