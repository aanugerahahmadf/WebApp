<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('reviews', 'order_id')) {
                    $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
                }
                if (! Schema::hasColumn('reviews', 'variation')) {
                    $table->string('variation')->nullable()->after('comment')->comment('Model atau variasi paket/katalog seperti di Shopee');
                }
                if (! Schema::hasColumn('reviews', 'specifications')) {
                    $table->json('specifications')->nullable()->after('variation')->comment('Rincian spesifikasi ulasan: kualitas, kesesuaian, layanan');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (Schema::hasColumn('reviews', 'order_id')) {
                    $table->dropForeign(['order_id']);
                    $table->dropColumn('order_id');
                }
                if (Schema::hasColumn('reviews', 'variation')) {
                    $table->dropColumn('variation');
                }
                if (Schema::hasColumn('reviews', 'specifications')) {
                    $table->dropColumn('specifications');
                }
            });
        }
    }
};
