<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_decoration_policies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Kebijakan Wedding Floral Decorations');
            $table->json('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_decoration_policies');
    }
};
