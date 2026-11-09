<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('discountable_type')->nullable()->change();
            $table->unsignedBigInteger('discountable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('discounts') || ! Schema::hasColumn('discounts', 'discountable_type')) {
            return;
        }

        // Discount voucher (era 2026_07_22_000002) sengaja tidak punya target
        // morph. Column NOT NULL pra-migrasi tidak bisa mewakili baris itu, dan
        // vouchers.discount_id yang menunjuknya ikut dilepas di rollback ini,
        // jadi barisnya memang yatim.
        DB::table('discounts')
            ->where(function ($query): void {
                $query->whereNull('discountable_type')->orWhereNull('discountable_id');
            })
            ->delete();

        Schema::table('discounts', function (Blueprint $table) {
            $table->string('discountable_type')->nullable(false)->change();
            $table->unsignedBigInteger('discountable_id')->nullable(false)->change();
        });
    }
};
