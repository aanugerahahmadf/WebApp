<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 20);
            $table->string('document_number', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('ai_document_type', 20)->nullable();
            $table->string('ai_document_number', 50)->nullable();
            $table->string('ai_reason')->nullable();
            $table->string('ai_reason_code', 40)->nullable();
            $table->json('ai_blocking_issue')->nullable();
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->boolean('ai_verified')->nullable();
            $table->boolean('ai_number_matches_profile')->nullable();
            $table->timestamp('ai_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document_type']);
            $table->unique('document_number');
        });

        // One-time compatibility import. New writes use identity_documents only.
        DB::table('users')->select([
            'id', 'ktp_number', 'sim_number', 'npwp_number', 'passport_number', 'ktp_photo',
        ])->orderBy('id')->each(function (object $user): void {
            foreach ([
                'ktp' => [$user->ktp_number ?? null, $user->ktp_photo ?? null],
                'sim' => [$user->sim_number ?? null, null],
                'npwp' => [$user->npwp_number ?? null, null],
                'passport' => [$user->passport_number ?? null, null],
            ] as $type => [$number, $photo]) {
                if ($number === null && $photo === null) {
                    continue;
                }
                DB::table('identity_documents')->insert([
                    'user_id' => $user->id,
                    'document_type' => $type,
                    'document_number' => $number,
                    'photo_path' => $photo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_documents');
    }
};
