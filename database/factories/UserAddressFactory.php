<?php

namespace Database\Factories;

use App\Models\User\User;
use App\Models\User\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $addresses = [
            ['city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'postal' => '12345'],
            ['city' => 'Jakarta Pusat', 'province' => 'DKI Jakarta', 'postal' => '10110'],
            ['city' => 'Bandung', 'province' => 'Jawa Barat', 'postal' => '40115'],
            ['city' => 'Surabaya', 'province' => 'Jawa Timur', 'postal' => '60237'],
            ['city' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'postal' => '55281'],
            ['city' => 'Denpasar', 'province' => 'Bali', 'postal' => '80234'],
            ['city' => 'Tangerang Selatan', 'province' => 'Banten', 'postal' => '15414'],
            ['city' => 'Bogor', 'province' => 'Jawa Barat', 'postal' => '16128'],
            ['city' => 'Bekasi', 'province' => 'Jawa Barat', 'postal' => '17142'],
            ['city' => 'Depok', 'province' => 'Jawa Barat', 'postal' => '16424'],
        ];

        $location = fake()->randomElement($addresses);

        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('628##########'),
            'address' => fake()->streetAddress() . ', ' . $location['city'],
            'city' => $location['city'],
            'province' => $location['province'],
            'postal_code' => $location['postal'],
            'type' => fake()->randomElement(['rumah', 'kantor', 'venue']),
            'is_default' => false,
        ];
    }

    /**
     * Alamat default.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Alamat tipe rumah.
     */
    public function home(): static
    {
        return $this->state(fn () => ['type' => 'rumah']);
    }

    /**
     * Alamat tipe kantor.
     */
    public function office(): static
    {
        return $this->state(fn () => ['type' => 'kantor']);
    }

    /**
     * Alamat tipe venue pernikahan.
     */
    public function venue(): static
    {
        return $this->state(fn () => [
            'type' => 'venue',
            'name' => fake()->randomElement([
                'Grand Ballroom Hotel', 'Plataran Cilandak', 'Glass House Bogor',
                'The Dharmawangsa', 'Gedung Sasono Utomo TMII',
            ]),
        ]);
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
