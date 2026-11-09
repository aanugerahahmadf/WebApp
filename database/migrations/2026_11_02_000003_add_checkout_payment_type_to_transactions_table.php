<?php

use App\Support\ForeignKeyIndexes\ForeignKeyIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History pembayaran mobile membutuhkan satu kolom yang menandai metode
     * checkout aktif per transaksi. `payment_method` berisi label display dari
     * gateway ("Virtual Account BRI", "QRIS") dan dapat ditimpa provider,
     * jadi nilai machine-readable tidak boleh bergantung padanya.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('checkout_payment_type')->nullable()->after('payment_method');
            $table->index(['user_id', 'checkout_payment_type']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'checkout_payment_type')) {
            return;
        }

        ForeignKeyIndexes::dropIndex('transactions', ['user_id', 'checkout_payment_type']);

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('checkout_payment_type');
        });
    }
};
