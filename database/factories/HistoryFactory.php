<?php

namespace Database\Factories;

use App\Models\History\History;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HistoryFactory extends Factory
{
    protected $model = History::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['topup', 'order', 'refund', 'payment']),
            'transaction_id' => fake()->unique()->numberBetween(1, 99999),
            'reference_number' => 'HIST-'.strtoupper(Str::random(10)),
            'amount' => fake()->randomFloat(2, 10000, 5000000),
            'info' => fake()->sentence(),
            'status' => fake()->randomElement(['success', 'pending', 'failed']),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }
}
