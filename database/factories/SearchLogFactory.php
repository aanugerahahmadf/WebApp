<?php

namespace Database\Factories;

use App\Models\Search\SearchLog;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchLog>
 */
class SearchLogFactory extends Factory
{
    protected $model = SearchLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $searchQueries = [
            // Produk
            'buket bunga mawar merah', 'dekorasi pelaminan minimalis', 'paket wedding outdoor',
            'bunga meja resepsi', 'flower arch pernikahan', 'buket lily putih elegan',
            'dekorasi backdrop simple', 'hiasan mobil pengantin', 'souvenir bunga kering',
            // Paket
            'paket gold wedding', 'paket platinum dekorasi', 'paket silver murah',
            'paket all inclusive pernikahan', 'dekorasi lengkap gedung',
            // Tema
            'tema rustic outdoor', 'dekorasi bohemian', 'tema garden party',
            'dekorasi mewah ballroom', 'tema minimalis modern',
            // Kota
            'dekorasi pernikahan jakarta', 'vendor bunga bandung', 'dekorasi surabaya',
            'florist yogyakarta', 'wedding decoration bali',
        ];

        return [
            'query' => fake()->randomElement($searchQueries),
            'user_id' => fake()->optional(0.6)->passthrough(User::factory()),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Log dari pengguna yang login.
     */
    public function byAuthenticatedUser(User|int|null $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user instanceof User ? $user->id : ($user ?? User::factory()),
        ]);
    }

    /**
     * Log dari pengunjung anonim (guest).
     */
    public function byGuest(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    /**
     * Pencarian berdasarkan kategori tema.
     */
    public function rusticSearch(): static
    {
        return $this->state(fn () => ['query' => 'dekorasi rustic outdoor garden']);
    }

    public function luxurySearch(): static
    {
        return $this->state(fn () => ['query' => 'paket mewah diamond wedding ballroom']);
    }
}
