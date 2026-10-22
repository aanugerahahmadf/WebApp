<?php

namespace Database\Factories;

use App\Models\User\User;
use App\Models\User\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Password terenkripsi yang di-cache.
     * Sesuai App\Support\PasswordPolicy (min 12 char, upper, lower, number, symbol).
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $fullName = "{$firstName} {$lastName}";
        $username = Str::lower($firstName) . fake()->unique()->numberBetween(100, 9999);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // yang akan hash otomatis. JANGAN Hash::make di sini (double-hash).
            'password' => '@FactoryPass123',
            'remember_token' => Str::random(10),
            'active_status' => true,
            'phone' => fake()->numerify('628##########'),
            'address' => fake()->address(),
            'balance' => fake()->randomFloat(2, 0, 5000000),
            'budget' => fake()->randomElement([15000000, 25000000, 50000000, 100000000, 200000000]),
            'wedding_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'theme_preference' => fake()->randomElement(['rustic', 'modern', 'classic', 'garden', 'minimalis', 'mewah']),
            'color_preference' => fake()->randomElement(['Pastel Pink & Gold', 'Earthy Green & White', 'Royal Navy & Silver', 'Burgundy & Champagne']),
            'event_concept' => fake()->randomElement(['Intimate Garden Party', 'Grand Ballroom Reception', 'Modern Minimalist Akad & Resepsi']),
            'dream_venue' => fake()->randomElement(['Plataran Cilandak', 'Glass House Bogor', 'Grand Ballroom Hotel Indonesia Kempinski', 'The Dharmawangsa Jakarta']),
            'dark_mode' => fake()->boolean(20) ? 1 : 0,
        ];
    }

    /**
     * Pengguna aktif / tidak aktif.
     */
    public function active(): static
    {
        return $this->state(fn () => ['active_status' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active_status' => false]);
    }

    /**
     * Email verifikasi.
     */
    public function verified(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * Pengguna yang sedang aktif merencanakan pernikahan.
     */
    public function planningWedding(): static
    {
        return $this->state(fn () => [
            'wedding_date' => now()->addMonths(fake()->numberBetween(2, 8)),
            'budget' => fake()->randomFloat(2, 50000000, 150000000),
            'theme_preference' => 'rustic',
            'event_concept' => 'Romantic Outdoor Garden Party',
        ]);
    }

    /**
     * Saldo dompet / wallet balance.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }

    /**
     * Mengaitkan alamat pengguna setelah dibuat.
     */
    public function withAddress(): static
    {
        return $this->afterCreating(function (User $user) {
            UserAddress::factory()->create([
                'user_id' => $user->id,
                'is_default' => true,
            ]);
        });
    }
}
