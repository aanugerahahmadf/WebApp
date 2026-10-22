<?php

namespace Database\Factories;

use App\Enums\PaymentStatus\PaymentStatus;
use App\Enums\TransactionType\TransactionType;
use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(TransactionType::cases());
        $amount = fake()->randomFloat(2, 500000, 25000000);
        $adminFee = fake()->randomElement([0, 2500, 5000, 7500, 10000]);
        $status = fake()->randomElement(PaymentStatus::cases());

        return [
            'user_id' => User::factory(),
            'order_id' => $type === TransactionType::ORDER ? Order::factory() : null,
            'type' => $type,
            'reference_number' => 'TRX-' . date('Ymd') . '-' . strtoupper(Str::random(8)),
            'amount' => $amount,
            'admin_fee' => $adminFee,
            'total_amount' => $amount + $adminFee,
            'payment_gateway' => fake()->randomElement(['manual', 'midtrans', 'xendit']),
            'payment_method' => fake()->randomElement([
                'bank_transfer', 'qris', 'virtual_account',
                'credit_card', 'gopay', 'ovo', 'dana',
            ]),
            'payment_url' => $status === PaymentStatus::PENDING
                ? 'https://payment.example.com/checkout/' . Str::random(20)
                : null,
            'status' => $status,
            'paid_at' => in_array($status, [PaymentStatus::SUCCESS]) ? now() : null,
            'notes' => fake()->optional(0.4)->randomElement([
                'Pembayaran dekorasi pelaminan Gold',
                'Top up saldo via QRIS',
                'DP 50% paket Platinum Wedding',
                'Pelunasan sisa pembayaran paket Silver',
            ]),
            'metadata' => [
                'customer_name' => fake()->name(),
                'customer_email' => fake()->email(),
                'customer_phone' => fake()->numerify('628##########'),
                'ip_address' => fake()->ipv4(),
            ],
        ];
    }

    /**
     * Status Transaksi.
     */
    public function success(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'payment_url' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PENDING,
            'paid_at' => null,
            'payment_url' => 'https://payment.example.com/checkout/' . Str::random(20),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PROCESSING,
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::FAILED,
            'paid_at' => null,
            'payment_url' => null,
            'notes' => 'Transaksi gagal: timeout koneksi ke payment gateway',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::EXPIRED,
            'paid_at' => null,
            'payment_url' => null,
            'notes' => 'Payment link sudah kadaluarsa (24 jam)',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::REFUNDED,
            'notes' => 'Refund diproses oleh admin',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::CANCELLED,
            'paid_at' => null,
            'payment_url' => null,
        ]);
    }

    /**
     * Tipe Transaksi.
     */
    public function topup(float $amount = 1000000): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::TOPUP,
            'order_id' => null,
            'amount' => $amount,
            'admin_fee' => 0,
            'total_amount' => $amount,
            'notes' => 'Top up saldo e-wallet',
        ]);
    }

    public function order(Order|int|null $order = null): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::ORDER,
            'order_id' => $order instanceof Order ? $order->id : ($order ?? Order::factory()),
        ]);
    }

    /**
     * Metode Pembayaran.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn () => [
            'payment_gateway' => 'manual',
            'payment_method' => 'bank_transfer',
        ]);
    }

    public function qris(): static
    {
        return $this->state(fn () => [
            'payment_gateway' => 'midtrans',
            'payment_method' => 'qris',
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'payment_gateway' => 'midtrans',
            'payment_method' => 'credit_card',
            'admin_fee' => 7500,
        ]);
    }

    public function eWallet(string $wallet = 'gopay'): static
    {
        return $this->state(fn () => [
            'payment_gateway' => 'midtrans',
            'payment_method' => $wallet,
        ]);
    }

    /**
     * Transaksi untuk user tertentu.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }

    /**
     * Transaksi bernilai tinggi (Luxury Wedding).
     */
    public function highValue(): static
    {
        return $this->state(function () {
            $amount = fake()->randomFloat(2, 25000000, 75000000);
            return [
                'amount' => $amount,
                'admin_fee' => 10000,
                'total_amount' => $amount + 10000,
            ];
        });
    }
}
