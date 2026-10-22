<?php

namespace Database\Factories;

use App\Models\History\History;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<History>
 */
class HistoryFactory extends Factory
{
    protected $model = History::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['topup', 'order', 'refund', 'payment']);

        $infoMessages = [
            'topup' => [
                'Top up saldo via Transfer BCA',
                'Top up saldo via QRIS Scan',
                'Top up saldo via kartu kredit BNI',
            ],
            'order' => [
                'Pembayaran pesanan dekorasi pelaminan Gold',
                'Pembayaran buket bunga mawar untuk acara pernikahan',
                'Pembayaran paket dekorasi outdoor garden party',
            ],
            'refund' => [
                'Refund pesanan ORD-2026-ABCDEF karena pembatalan',
                'Refund sebagian untuk penyesuaian paket dekorasi',
                'Pengembalian dana deposit acara dibatalkan',
            ],
            'payment' => [
                'Pembayaran DP 50% paket Platinum Wedding',
                'Pelunasan sisa pembayaran paket Silver',
                'Cicilan kedua paket dekorasi Diamond VIP',
            ],
        ];

        $status = fake()->randomElement(['success', 'pending', 'failed']);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'transaction_id' => fake()->unique()->numberBetween(10000, 99999),
            'reference_number' => strtoupper($type[0]) . 'RF-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'amount' => fake()->randomFloat(2, 50000, 15000000),
            'info' => fake()->randomElement($infoMessages[$type]),
            'status' => $status,
            'notes' => $status === 'failed'
                ? fake()->randomElement(['Saldo tidak cukup', 'Timeout koneksi bank', 'Verifikasi gagal'])
                : fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Riwayat top up saldo.
     */
    public function topup(float $amount = 500000): static
    {
        return $this->state(fn () => [
            'type' => 'topup',
            'amount' => $amount,
            'info' => 'Top up saldo via Transfer Bank',
            'status' => 'success',
        ]);
    }

    /**
     * Riwayat pembayaran pesanan.
     */
    public function orderPayment(float $amount = 5000000): static
    {
        return $this->state(fn () => [
            'type' => 'order',
            'amount' => $amount,
            'info' => 'Pembayaran pesanan dekorasi pernikahan',
            'status' => 'success',
        ]);
    }

    /**
     * Riwayat refund.
     */
    public function refund(float $amount = 2000000): static
    {
        return $this->state(fn () => [
            'type' => 'refund',
            'amount' => $amount,
            'info' => 'Refund pesanan karena pembatalan',
            'status' => 'success',
        ]);
    }

    /**
     * Status berhasil / sukses.
     */
    public function success(): static
    {
        return $this->state(fn () => ['status' => 'success', 'notes' => null]);
    }

    /**
     * Status pending / menunggu.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'notes' => 'Menunggu konfirmasi pembayaran']);
    }

    /**
     * Status gagal.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'notes' => 'Transaksi gagal: saldo tidak mencukupi',
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
