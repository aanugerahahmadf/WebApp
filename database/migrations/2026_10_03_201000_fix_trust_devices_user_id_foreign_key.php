<?php

/**
 * Membetulkan foreign key trust_devices.user_id.
 *
 * Migrasi aslinya memakai foreignUuid() sehingga user_id menjadi char(36),
 * sedangkan users.id adalah bigint. MySQL menolaknya dengan errno 3780
 * ("Referencing column 'user_id' and referenced column 'id' in foreign key
 * constraint are incompatible") -- jadi setiap operasi pada tabel
 * trust_devices gagal, termasuk yang dilakukan mix-code/filament-multi-2fa.
 *
 * Tabelnya masih kosong (dibuat hari ini, belum dipakai), jadi aman
 * dibangun ulang dengan tipe yang benar.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trust_devices')) {
            return;
        }

        // foreignUuid -> foreignId. whole table dibangun ulang karena
        // menukar tipe kolom jadi foreign key tidak bisa dilakukan di_place.
        if (Schema::getColumnType('trust_devices', 'user_id') === 'bigint') {
            return;
        }

        Schema::drop('trust_devices');

        Schema::create('trust_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('device_signature')->index();
            $table->timestamp('expires_at');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->index(['user_id', 'device_signature']);
            $table->index(['user_id', 'device_signature', 'expires_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trust_devices')) {
            return;
        }

        Schema::dropIfExists('trust_devices');
    }
};