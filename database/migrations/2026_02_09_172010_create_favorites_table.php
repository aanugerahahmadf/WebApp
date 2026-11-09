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
        Schema::create('Favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nama fisik tabel bisa 'Favorites' (era lama) atau 'favorites'
        // (setelah 2026_11_03_000001); PostgreSQL membedakan keduanya.
        foreach (Schema::getTables() as $table) {
            if (strtolower($table['name']) === 'favorites') {
                Schema::drop($table['name']);

                return;
            }
        }
    }
};
