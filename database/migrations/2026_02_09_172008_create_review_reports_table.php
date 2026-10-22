<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the review_reports table used for reporting abusive reviews.
 *
 * review_votes and review_replies are intentionally NOT created here:
 *   - review_votes      -> 2026_08_25_000002_create_review_votes_table.php
 *   - review_replies    -> 2026_08_25_000003_create_review_replies_table.php
 * Creating them again here made the whole test suite fail with
 * `table "review_replies" already exists`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('review_id');
            $table->index('reporter_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
