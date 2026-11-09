<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order adalah riwayat pembayaran: penghapusan oleh user hanya menyembunyikan
     * record dari history. Baris order, transaksi, VA/QRIS, dan webhook BRI yang
     * sudah terbit harus tetap ada untuk audit dan laporan.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
