<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each catalog owns its Standard tier price. Older seed data assigned the
     * same fixed value to every Standard package, so copy the catalog's
     * original values into that catalog's Standard tier once.
     */
    public function up(): void
    {
        DB::table('catalogs')
            ->select(['id', 'price', 'discount_price'])
            ->orderBy('id')
            ->each(function (object $catalog): void {
                DB::table('packages')
                    ->where('catalog_id', $catalog->id)
                    ->where('name_translations->en', 'Standard Flower Decoration Package')
                    ->update([
                        'price' => $catalog->price,
                        'discount_price' => $catalog->discount_price,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Standard tier prices are catalog-specific by design. There is no
        // safe generic rollback for the previous incorrect global values.
    }
};
