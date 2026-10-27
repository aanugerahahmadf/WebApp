<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A VA method must not carry the settlement account as if it were a
        // customer's VA. The provider returns the real VA after an order is
        // created; the merchant account remains in config/bri.php only.
        DB::table('payment_methods')
            ->where('type', 'virtual_account')
            ->update([
                'account_number' => null,
                'account_holder' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally do not restore a merchant account into a customer
        // payment method. The value belongs in deployment configuration.
    }
};
