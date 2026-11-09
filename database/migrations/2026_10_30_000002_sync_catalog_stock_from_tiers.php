<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogs') || ! Schema::hasTable('packages')) {
            return;
        }

        DB::table('catalogs')->orderBy('id')->eachById(function (object $catalog): void {
            $stock = (int) DB::table('packages')
                ->where('catalog_id', $catalog->id)
                ->sum('stock');

            DB::table('catalogs')->where('id', $catalog->id)->update(['stock' => $stock]);
        });
    }

    public function down(): void
    {
        // The previous catalog stock values are not authoritative anymore.
    }
};
