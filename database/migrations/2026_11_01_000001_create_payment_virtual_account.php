<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_virtual_accounts')) {
            return;
        }

        Schema::create('payment_virtual_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('virtual_account_no', 64)->nullable();
            $table->string('virtual_account_name', 100)->nullable();
            $table->timestamp('virtual_account_expiry')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_virtual_accounts');
    }
};
