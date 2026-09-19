<?php

namespace Database\Seeders;

use App\Models\Discount\Discount;
use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [];

        $packageIds = Package::take(3)->pluck('id');
        foreach ($packageIds as $i => $id) {
            $discounts[] = [
                'description' => 'Diskon khusus untuk paket pernikahan pilihan',
                'discountable_type' => Package::class,
                'discountable_id' => $id,
                'type' => 'percentage',
                'value' => [10, 15, 20][$i],
                'min_purchase' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(3),
                'is_active' => true,
            ];
        }

        $productIds = Product::take(4)->pluck('id');
        foreach ($productIds as $i => $id) {
            $discounts[] = [
                'description' => 'Diskon untuk produk dekorasi pernikahan',
                'discountable_type' => Product::class,
                'discountable_id' => $id,
                'type' => 'fixed',
                'value' => [25_000, 50_000, 75_000, 100_000][$i],
                'min_purchase' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(2),
                'is_active' => true,
            ];
        }

        foreach ($discounts as $data) {
            Discount::updateOrCreate(
                [
                    'discountable_type' => $data['discountable_type'],
                    'discountable_id' => $data['discountable_id'],
                ],
                $data
            );
        }

        $this->command->info('✅ '.count($discounts).(' diskon item berhasil dibuat.'));
    }
}
