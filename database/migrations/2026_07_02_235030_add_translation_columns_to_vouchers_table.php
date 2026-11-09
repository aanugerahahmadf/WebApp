<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->json('description_translations')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        // 2026_10_17_000001 ikut menambahkan kolom yang sama secara kondisional,
        // jadi rollback bisa sudah menghapusnya lebih dulu.
        if (! Schema::hasColumn('vouchers', 'description_translations')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['description_translations']);
        });
    }
};