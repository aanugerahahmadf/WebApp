<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->applyPostgres(['unpaid', 'pending', 'paid', 'failed', 'refunded', 'cancelled']);

            return;
        }

        // SQLite does not support MODIFY COLUMN — skip on SQLite (used in testing)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `payment_status` ENUM('unpaid','pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE orders SET payment_status = 'unpaid' WHERE payment_status = 'cancelled'");
            $this->applyPostgres(['unpaid', 'pending', 'paid', 'failed', 'refunded']);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert rows with 'cancelled' back to 'unpaid' before removing the value
        DB::statement("UPDATE `orders` SET `payment_status` = 'unpaid' WHERE `payment_status` = 'cancelled'");
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `payment_status` ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid'");
    }

    /**
     * PostgreSQL punya `$table->enum()` sebagai varchar + CHECK constraint, jadi
     * menambah nilai enum berarti mengganti constraint-nya, bukan MODIFY COLUMN.
     */
    private function applyPostgres(array $allowed): void
    {
        $quoted = implode(', ', array_map(fn (string $value) => "'".$value."'", $allowed));

        $constraints = DB::select(
            "select conname from pg_constraint
             where conrelid = 'orders'::regclass and contype = 'c'
               and pg_get_constraintdef(oid) like '%payment_status%'"
        );

        foreach ($constraints as $constraint) {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT "'.$constraint->conname.'"');
        }

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ({$quoted}))");
    }
};
