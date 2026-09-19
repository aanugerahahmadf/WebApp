<?php

namespace Database\Seeders;

use App\Models\Discount\Discount;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VoucherSeeder extends Seeder
{
    use TranslatesContent;

    private function randomCode(): string
    {
        return strtoupper(Str::random(16));
    }

    public function run(): void
    {
        // ── 0. CREATE TEMPLATE DISCOUNTS FOR VOUCHERS ────────────────
        // Each voucher links to one of these discounts.
        // Discount holds the actual rules (type, value, min_purchase).

        $voucherDiscounts = [
            ['key' => 'pct_10',  'type' => 'percentage', 'value' => 10,  'min_purchase' => 500_000],
            ['key' => 'fix_50k', 'type' => 'fixed',      'value' => 50_000,  'min_purchase' => 1_000_000],
            ['key' => 'pct_15',  'type' => 'percentage', 'value' => 15,  'min_purchase' => 2_000_000],
            ['key' => 'fix_100k','type' => 'fixed',      'value' => 100_000, 'min_purchase' => 0],
            ['key' => 'pct_20',  'type' => 'percentage', 'value' => 20,  'min_purchase' => 1_500_000],
            ['key' => 'pct_25',  'type' => 'percentage', 'value' => 25,  'min_purchase' => 3_000_000],
            ['key' => 'fix_200k','type' => 'fixed',      'value' => 200_000, 'min_purchase' => 2_500_000],
            ['key' => 'pct_30',  'type' => 'percentage', 'value' => 30,  'min_purchase' => 1_000_000],
            ['key' => 'fix_75k', 'type' => 'fixed',      'value' => 75_000,  'min_purchase' => 500_000],
            ['key' => 'pct_15e', 'type' => 'percentage', 'value' => 15, 'min_purchase' => 5_000_000],
        ];

        $discountMap = [];
        foreach ($voucherDiscounts as $d) {
            $discount = Discount::updateOrCreate(
                [
                    'type' => $d['type'],
                    'value' => $d['value'],
                    'min_purchase' => $d['min_purchase'],
                    'discountable_type' => null,
                    'discountable_id' => null,
                ],
                [
                    'is_active' => true,
                ]
            );
            $discountMap[$d['key']] = $discount->id;
        }

        $this->command->info('✅ Diskon untuk voucher berhasil disiapkan.');

        // ── 1. VOUCHER PUBLIC (is_global = true) ──────────────────────────
        // Each voucher is just a 16-char random code + FK to discount.

        $publicDiscounts = [
            ['key' => 'pct_10',  'max_uses' => 500],
            ['key' => 'fix_50k', 'max_uses' => 200],
            ['key' => 'pct_15',  'max_uses' => 100],
            ['key' => 'fix_100k','max_uses' => 50],
            ['key' => 'pct_20',  'max_uses' => 30],
        ];

        $publicNames = [
            'Welcome Discount',
            'Hemat 50 Ribu',
            'Promo 2026',
            'Gratis 100 Ribu',
            'Flash Sale',
        ];

        foreach ($publicDiscounts as $i => $data) {
            $disc = Discount::find($discountMap[$data['key']]);
            Voucher::updateOrCreate(
                ['code' => $this->randomCode()],
                [
                    'name' => $publicNames[$i],
                    'discount_id' => $discountMap[$data['key']],
                    'description' => 'Voucher promosi publik',
                    'is_active' => true,
                    'is_global' => true,
                    'max_uses' => $data['max_uses'],
                    'uses_count' => 0,
                ]
            );
        }

        $this->command->info('✅ 5 voucher PUBLIC (16-karakter) berhasil dibuat.');

        // ── 2. VOUCHER PER-USER (is_global = false) ───────────────────────

        $perUserDiscounts = [
            ['key' => 'pct_25',  'desc' => 'Voucher eksklusif member Gold'],
            ['key' => 'fix_200k','desc' => 'Hadiah loyalitas pelanggan'],
            ['key' => 'pct_30',  'desc' => 'Voucher spesial ulang tahun'],
            ['key' => 'fix_75k', 'desc' => 'Bonus referral'],
            ['key' => 'pct_15e', 'desc' => 'Early bird booking'],
        ];

        $perUserNames = [
            'Gold Member',
            'Loyalty Reward',
            'Birthday Special',
            'Referral Bonus',
            'Early Bird',
        ];

        $createdPerUser = [];
        foreach ($perUserDiscounts as $i => $data) {
            $v = Voucher::updateOrCreate(
                ['code' => $this->randomCode()],
                [
                    'name' => $perUserNames[$i],
                    'discount_id' => $discountMap[$data['key']],
                    'description' => $data['desc'],
                    'is_active' => true,
                    'is_global' => false,
                    'max_uses' => null,
                    'uses_count' => 0,
                ]
            );
            $createdPerUser[] = $v;
        }

        $this->command->info('✅ 5 voucher PER-USER (16-karakter) berhasil dibuat.');

        // ── 3. ASSIGN VOUCHER PER-USER KE USER ────────────────────────────

        $customers = User::role('customer')->get();

        if ($customers->isEmpty()) {
            $customers = User::whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super_admin');
            })->get();
        }

        if ($customers->isEmpty()) {
            $this->command->warn('⚠️  Tidak ada user customer ditemukan. Voucher per-user tidak di-assign.');
            return;
        }

        $assignedCount = 0;
        foreach ($customers as $user) {
            foreach ($createdPerUser as $voucher) {
                $voucher->assignToUser($user->id);
                $assignedCount++;
            }
        }

        $this->command->info("✅ {$assignedCount} assignment voucher per-user ke {$customers->count()} user berhasil.");
    }
}
