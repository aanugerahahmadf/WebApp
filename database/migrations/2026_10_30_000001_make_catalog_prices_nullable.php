<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogs are parent/visual entities. Pricing is owned by packages
     * (tiers), so a catalog may be created before its tiers are configured.
     */
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->nullable()->change();
            $table->decimal('discount_price', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing rows may legitimately have no catalog price after this
        // migration, therefore the reverse migration does not make columns
        // non-nullable again.
    }
};
