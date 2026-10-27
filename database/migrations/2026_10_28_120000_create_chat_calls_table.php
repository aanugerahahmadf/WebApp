<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_calls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('inbox_id')->constrained('fm_inboxes')->cascadeOnDelete();
            $table->foreignId('caller_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('ringing');
            $table->json('offer')->nullable();
            $table->json('answer')->nullable();
            $table->json('ice_candidates')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['inbox_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_calls');
    }
};
