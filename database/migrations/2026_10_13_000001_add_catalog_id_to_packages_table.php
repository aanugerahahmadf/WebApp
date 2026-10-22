<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package becomes a "tier" of a Catalog.
 *
 * Before this change a Package was a standalone, separately browsable entity
 * with its own page. Packages are now only reachable from inside a Catalog
 * detail page, where the visitor picks a tier (Standar / Sedang / Pelaminan /
 * Tunangan / Lengkap) and the price follows that tier.
 *
 * catalog_id is nullable so existing rows stay valid; it is backfilled from
 * the matching category by `catalog:attach-package-tiers`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'catalog_id')) {
            Schema::table('packages', function (Blueprint $table) {
                // onDelete('null'): deleting a catalog must not delete tiers,
                // otherwise historical orders lose their tier reference.
                $table->foreignId('catalog_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('products')
                    ->nullOnDelete();

                // Deterministic tier ordering (Standar -> Sedang -> Pelaminan -> ...).
                $table->unsignedSmallInteger('sort_order')
                    ->nullable()
                    ->after('catalog_id');
            });

            // SQLite cannot add a FK constraint to an existing table; drop the
            // index Laravel created with the column and rely on the index only.
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                Schema::table('packages', function (Blueprint $table) {
                    $table->dropForeign(['catalog_id']);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'catalog_id')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropForeign(['catalog_id']);
                $table->dropColumn(['catalog_id', 'sort_order']);
            });
        }
    }
};
