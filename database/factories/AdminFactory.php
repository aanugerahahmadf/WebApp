<?php

namespace Database\Factories;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory untuk baris `admins`.
 *
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * Hash di-cache per-process agar bcrypt tidak dipanggil berulang kali.
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

        return [
            'full_name' => "{$firstName} {$lastName}",
            'first_name' => $firstName,
            'last_name' => $lastName,
            'mid_name' => null,
            'username' => 'admin' . Str::random(8),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Password MENTAH — model Admin punya cast 'password' => 'hashed'
            // yang akan hash otomatis. JANGAN Hash::make di sini (double-hash).
            'password' => 'Password123!',
            'remember_token' => Str::random(10),
            'position' => fake()->randomElement([
                'Administrator',
                'Supervisor Dekorasi',
                'Manajer Operasional',
                'Staf Keuangan',
                'Customer Service Lead',
                'Desainer Floral',
                'Koordinator Venue',
                'Quality Assurance',
            ]),
            'department' => fake()->randomElement([
                'Engineering',
                'Catalog & Produk',
                'Keuangan & Akuntansi',
                'Operasional Lapangan',
                'Customer Service',
                'Marketing & Promosi',
                'Human Resources',
                'Quality Control',
            ]),
            'phone' => fake()->numerify('628##########'),
            'whatsapp' => fake()->numerify('628##########'),
            'active_status' => true,
            'notes' => null,
            'user_id' => null,
        ];
    }

    /**
     * Akun yang tidak aktif / tidak boleh masuk.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['active_status' => false]);
    }

    /**
     * Akun aktif.
     */
    public function active(): static
    {
        return $this->state(fn (): array => ['active_status' => true]);
    }

    /**
     * Akun yang emailnya belum diverifikasi.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /**
     * Admin Super Admin (Assign role setelah create).
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (Admin $admin) {
            $admin->assignRole('super_admin');
        })->state(fn (): array => [
            'position' => 'Super Administrator',
            'department' => 'Engineering',
        ]);
    }

    /**
     * Admin Departemen Keuangan.
     */
    public function finance(): static
    {
        return $this->state(fn (): array => [
            'position' => 'Staf Keuangan',
            'department' => 'Keuangan & Akuntansi',
        ]);
    }

    /**
     * Admin Departemen Operasional (Lapangan).
     */
    public function operational(): static
    {
        return $this->state(fn (): array => [
            'position' => 'Koordinator Venue',
            'department' => 'Operasional Lapangan',
        ]);
    }

    /**
     * Admin Customer Service.
     */
    public function customerService(): static
    {
        return $this->state(fn (): array => [
            'position' => 'Customer Service Lead',
            'department' => 'Customer Service',
        ]);
    }

    /**
     * Admin Desainer Floral.
     */
    public function floralDesigner(): static
    {
        return $this->state(fn (): array => [
            'position' => 'Desainer Floral',
            'department' => 'Catalog & Produk',
        ]);
    }

    /**
     * Admin dengan catatan memo internal.
     */
    public function withNotes(string $notes = 'Catatan internal admin'): static
    {
        return $this->state(fn (): array => ['notes' => $notes]);
    }
}