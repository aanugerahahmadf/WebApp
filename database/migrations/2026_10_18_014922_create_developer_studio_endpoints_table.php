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
        Schema::create('developer_studio_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index(); // api, route, controller, graphql, grpc, capacitor, flutter
            $table->string('method', 100)->nullable()->index(); // GET, POST, PUT, DELETE, RPC, QUERY, etc.
            $table->string('uri', 500)->index();
            $table->string('name', 255)->nullable()->index();
            $table->text('action')->nullable();
            $table->string('controller_class', 255)->nullable()->index();
            $table->json('middleware')->nullable();
            $table->string('domain', 255)->nullable();
            $table->text('file_path')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_studio_endpoints');
    }
};
