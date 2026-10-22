<?php

namespace Database\Seeders;

use App\Models\Discount\Discount;
use App\Models\Catalog\Catalog;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [];

        $catalogIds = Catalog::take(7)->pluck('id');
        foreach ($catalogIds as $i => $id) {
            $values = [10, 15, 20, 25_000, 50_000, 75_000, 100_000];
            $types = ['percentage', 'percentage', 'percentage', 'fixed', 'fixed', 'fixed', 'fixed'];
            $discounts[] = [
                'description' => 'Diskon untuk dekorasi pernikahan pilihan',
                'discountable_type' => Catalog::class,
                'discountable_id' => $id,
                'type' => $types[$i],
                'value' => $values[$i],
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
