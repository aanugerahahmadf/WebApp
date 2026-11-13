<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'shipping_courier')) {
                $table->string('shipping_courier', 50)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('orders', 'shipping_courier_name')) {
                $table->string('shipping_courier_name')->nullable()->after('shipping_courier');
            }
            if (! Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('shipping_courier_name');
            }
            if (! Schema::hasColumn('orders', 'tracking_url')) {
                $table->string('tracking_url', 2048)->nullable()->after('tracking_number');
            }
            if (! Schema::hasColumn('orders', 'shipping_status')) {
                $table->string('shipping_status', 30)->nullable()->after('tracking_url');
            }
            if (! Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('shipping_status');
            }
            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
            if (! Schema::hasColumn('orders', 'shipping_notes')) {
                $table->text('shipping_notes')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $columns = [
                'shipping_courier',
                'shipping_courier_name',
                'tracking_number',
                'tracking_url',
                'shipping_status',
                'shipped_at',
                'delivered_at',
                'shipping_notes',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
