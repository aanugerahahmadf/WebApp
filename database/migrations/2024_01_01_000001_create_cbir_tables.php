<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // CBIR Searches table
        Schema::create('cbir_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('query_image_url');
            $table->integer('results_count')->default(0);
            $table->float('top_similarity', 8, 2)->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        // CBIR Search Results table
        Schema::create('cbir_search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained('cbir_searches')->onDelete('cascade');
            $table->string('type'); // 'catalog' or 'package'
            $table->unsignedBigInteger('item_id');
            $table->float('similarity', 8, 2);
            $table->float('score', 8, 6);
            $table->timestamps();

            $table->index('search_id');
            $table->index(['type', 'item_id']);
        });

        // CBIR Feedback table
        Schema::create('cbir_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('search_id')->constrained('cbir_searches')->onDelete('cascade');
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->boolean('is_relevant');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('search_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbir_feedback');
        Schema::dropIfExists('cbir_search_results');
        Schema::dropIfExists('cbir_searches');
    }
};