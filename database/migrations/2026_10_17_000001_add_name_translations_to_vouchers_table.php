<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'name_translations')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->json('name_translations')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('vouchers', 'description_translations')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->json('description_translations')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vouchers', 'name_translations')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('name_translations');
            });
        }

        if (Schema::hasColumn('vouchers', 'description_translations')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('description_translations');
            });
        }
    }
};