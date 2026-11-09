<?php

use App\Support\ForeignKeyIndexes\ForeignKeyIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dukungan kartu "Komentar Penilaian Katalog" dan "Komentar Penilaian Katalog Saya".
     *
     * `order_id` dan `variation`/`specifications` sudah ditambahkan oleh
     * migration 2026_11_02_000001. Di sini yang kurang:
     *
     *   - `is_anonymous`, supaya ulasan bisa tampil dengan nama tersamar
     *     seperti di Shopee tanpa menghapus identitas aslinya dari database;
     *   - index untuk daftar ulasan: urut waktu per user, urut waktu per
     *     katalog, filter rating, dan urut "paling membantu".
     */
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'is_anonymous')) {
                $table->boolean('is_anonymous')
                    ->default(false)
                    ->after('helpful_count')
                    ->comment('Sembunyikan identitas penulis di daftar ulasan publik');
            }
        });

        foreach ($this->indexes() as $name => $columns) {
            if (Schema::hasIndex('reviews', $name)) {
                continue;
            }

            Schema::table('reviews', function (Blueprint $table) use ($name, $columns): void {
                $table->index($columns, $name);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        foreach ($this->indexes() as $name => $columns) {
            ForeignKeyIndexes::dropIndex('reviews', $columns, $name);
        }

        Schema::table('reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('reviews', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function indexes(): array
    {
        return [
            'reviews_user_created_at_index'       => ['user_id', 'created_at'],
            'reviews_catalog_created_at_index'    => ['catalog_id', 'created_at'],
            'reviews_package_created_at_index'    => ['package_id', 'created_at'],
            'reviews_rating_index'                => ['rating'],
            'reviews_helpful_count_index'         => ['helpful_count'],
        ];
    }
};
