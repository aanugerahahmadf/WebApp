<?php

use App\Support\ForeignKeyIndexes\ForeignKeyIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('transactions', ['virtual_account_no']);
        $this->addIndexIfMissing('transactions', ['status']);
        $this->addIndexIfMissing('user_vouchers', ['order_id', 'user_id']);
        $this->addIndexIfMissing('notifications', ['notifiable_type', 'notifiable_id', 'read_at']);
        $this->addIndexIfMissing('orders', ['user_id', 'status']);
        $this->addIndexIfMissing('fm_messages', ['inbox_id', 'created_at']);
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->dropIndexIfExists('transactions', ['virtual_account_no']);
        $this->dropIndexIfExists('transactions', ['status']);
        $this->dropIndexIfExists('user_vouchers', ['order_id', 'user_id']);
        $this->dropIndexIfExists('notifications', ['notifiable_type', 'notifiable_id', 'read_at']);
        $this->dropIndexIfExists('orders', ['user_id', 'status']);
        $this->dropIndexIfExists('fm_messages', ['inbox_id', 'created_at']);

        Schema::enableForeignKeyConstraints();
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns): void
    {
        if (! $this->canIndex($table, $columns) || Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->index($columns);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropIndexIfExists(string $table, array $columns): void
    {
        ForeignKeyIndexes::dropIndex($table, $columns);
    }

    /**
     * @param  list<string>  $columns
     */
    private function canIndex(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
