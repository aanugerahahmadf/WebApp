<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;

/**
 * Kolom 2FA milik package mix-code/filament-multi-2fa.
 *
 * partly sudah ada: tabel trust_devices dibuat duluan, tapi kolom users-nya
 * belum. Migrasi ini pakai hasColumn() seperti stub package, jadi:
 *   - two_factor_secret yang sudah ada (dari 2026_08_26) tidak di-ulang;
 *   - migration ini aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_type')) {
                $table->string('two_factor_type')
                    ->default(TwoFactorAuthType::None->value)
                    ->after('two_factor_secret');
            }

            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')
                    ->nullable()
                    ->after('two_factor_type');
            }

            if (! Schema::hasColumn('users', 'two_factor_sent_at')) {
                $table->timestamp('two_factor_sent_at')
                    ->nullable()
                    ->after('two_factor_recovery_codes');
            }

            if (! Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')
                    ->nullable()
                    ->after('two_factor_sent_at');
            }

            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')
                    ->nullable()
                    ->after('two_factor_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'two_factor_type',
                'two_factor_recovery_codes',
                'two_factor_sent_at',
                'two_factor_expires_at',
                'two_factor_confirmed_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};