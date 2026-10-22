<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complete the Product -> Catalog rename at the database level.
 *
 * Everything the app owns is now called "catalog": the table, the foreign key
 * column, the polymorphic type strings and the category discriminator. The morph
 * alias is normalised onto `App\Models\Catalog`, which is what
 * App\Models\Traits\LegacyMorphClass emits for App\Models\Catalog\Catalog — the
 * same shape every other model in this project already uses.
 *
 * Ordering matters: the table has to be renamed *before* the FKs are re-pointed
 * at it, and the columns have to be renamed before their constraints are
 * rebuilt. Every step is guarded so a re-run after a partial failure converges.
 */
return new class extends Migration
{
    /** Tables whose FK points at the renamed catalog table. */
    protected const FK_TABLES = ['carts', 'orders', 'reviews', 'wishlists', 'packages'];

    public function up(): void
    {
        // ---------------------------------------------------------------
        // 1. Drop the constraints while the old names are still in place.
        // ---------------------------------------------------------------
        foreach (self::FK_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) {
                $this->dropForeignKey($table, 'product_id');
            }
        }

        // ---------------------------------------------------------------
        // 2. Rename the table first so step 4 can re-point the FKs.
        // ---------------------------------------------------------------
        if (Schema::hasTable('products') && ! Schema::hasTable('catalogs')) {
            Schema::rename('products', 'catalogs');
        }

        // ---------------------------------------------------------------
        // 3. Rename the FK columns.
        // ---------------------------------------------------------------
        foreach (['carts', 'orders', 'reviews', 'wishlists'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_id')) {
                continue;
            }

            Schema::table($table, fn ($blueprint) => $blueprint->renameColumn('product_id', 'catalog_id'));
        }

        // ---------------------------------------------------------------
        // 4. Rebuild the constraints against the renamed table.
        // ---------------------------------------------------------------
        foreach (['carts', 'orders', 'reviews', 'wishlists'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'catalog_id')) {
                continue;
            }

            $this->dropForeignKey($table, 'catalog_id');

            Schema::table($table, fn ($blueprint) => $blueprint->foreign('catalog_id')
                ->references('id')
                ->on('catalogs')
                ->nullOnDelete());
        }

        // packages.catalog_id was created against "products" and MySQL does not
        // always follow RENAME in constraint metadata — rebuild it explicitly.
        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'catalog_id')) {
            $this->dropForeignKey('packages', 'catalog_id');

            Schema::table('packages', fn ($blueprint) => $blueprint->foreign('catalog_id')
                ->references('id')
                ->on('catalogs')
                ->nullOnDelete());
        }

        // ---------------------------------------------------------------
        // 5. Rename the leftover `products_*` index names.
        // ---------------------------------------------------------------
        $this->renameIndex('catalogs', 'products_category_id_foreign', 'catalogs_category_id_foreign');
        foreach (['carts', 'orders', 'reviews', 'wishlists'] as $table) {
            $this->renameIndex($table, "{$table}_product_id_foreign", "{$table}_catalog_id_foreign");
        }

        // ---------------------------------------------------------------
        // 6. Data remaps.
        // ---------------------------------------------------------------
        // categories.type is the Catalog-vs-Package discriminator.
        DB::table('categories')->where('type', 'product')->update(['type' => 'catalog']);

        $this->remapMorphType('media', 'model_type');
        $this->remapMorphType('discounts', 'discountable_type');
        $this->remapMorphType('reports', 'reportable_type');
        $this->remapMorphType('model_has_roles', 'model_type');
        $this->remapMorphType('model_has_permissions', 'model_type');
        $this->remapMorphType('activity_log', 'subject_type');

        $this->remapToken('cbir_search_results', 'type');
        $this->remapToken('cbir_feedback', 'item_type');
    }

    public function down(): void
    {
        $this->remapToken('cbir_search_results', 'type', 'catalog', 'product');
        $this->remapToken('cbir_feedback', 'item_type', 'catalog', 'product');

        $this->remapMorphType('media', 'model_type', 'App\\Models\\Catalog', 'App\\Models\\Product');
        $this->remapMorphType('discounts', 'discountable_type', 'App\\Models\\Catalog', 'App\\Models\\Product');

        DB::table('categories')->where('type', 'catalog')->update(['type' => 'product']);

        foreach (['carts', 'orders', 'reviews', 'wishlists'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'catalog_id')) {
                $this->dropForeignKey($table, 'catalog_id');
            }
        }

        if (Schema::hasTable('catalogs') && ! Schema::hasTable('products')) {
            Schema::rename('catalogs', 'products');
        }

        foreach (['carts', 'orders', 'reviews', 'wishlists'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'catalog_id')) {
                continue;
            }

            Schema::table($table, fn ($blueprint) => $blueprint->renameColumn('catalog_id', 'product_id'));

            Schema::table($table, fn ($blueprint) => $blueprint->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete());
        }

        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'catalog_id')) {
            $this->dropForeignKey('packages', 'catalog_id');

            Schema::table('packages', fn ($blueprint) => $blueprint->foreign('catalog_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete());
        }
    }

    /**
     * Rename whichever `{table}_{column}_foreign` constraint exists, tolerating
     * a schema where the constraint was never created.
     */
    protected function dropForeignKey(string $table, string $column): void
    {
        foreach ([
            "{$table}_{$column}_foreign",
            "{$table}_".str_replace('_id', '', $column)."_foreign",
        ] as $name) {
            try {
                Schema::table($table, fn ($blueprint) => $blueprint->dropForeign($name));

                return;
            } catch (\Throwable $e) {
                // Not present under that name — try the next candidate.
            }
        }
    }

    protected function renameIndex(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, fn ($blueprint) => $blueprint->renameIndex($from, $to));
        } catch (\Throwable $e) {
            // No index under that name; nothing to rename.
        }
    }

    /**
     * Point every historical morph alias at `App\Models\Catalog`.
     *
     * Three spellings existed over time: the forced flat alias
     * `App\Models\Product`, the original FQCN `App\Models\Product\Product`, and
     * the package counterpart `App\Models\Package\Package`. All become the flat
     * `App\Models\Catalog` / `App\Models\Package` form the LegacyMorphClass
     * trait emits.
     */
    protected function remapMorphType(string $table, string $column, ?string $to = null, ?string $from = null): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $to ??= 'App\\Models\\Catalog';

        $map = $from !== null
            ? [$from => $to]
            : [
                'App\\Models\\Product' => $to,
                'App\\Models\\Product\\Product' => $to,
                'App\\Models\\Package\\Package' => 'App\\Models\\Package',
            ];

        foreach ($map as $old => $new) {
            DB::table($table)->where($column, $old)->update([$column => $new]);
        }
    }

    /**
     * Swap the bare "product" discriminator token for "catalog".
     */
    protected function remapToken(string $table, string $column, string $from = 'product', string $to = 'catalog'): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $from)->update([$column => $to]);
    }
};
