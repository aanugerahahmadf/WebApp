<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan hasil verifikasi dokumen identitas (KTP / SIM / NPWP / Paspor)
 * dari AI Core endpoint /api/ktp/verify.
 *
 * Sebelumnya TIDAK ADA kolom sama sekali untuk ini: ProfileController::uploadKtp()
 * hanya menyimpan path foto, jadi penolakan AI tidak meninggalkan jejak apa pun
 * dan `document_type` hasil deteksi tidak pernah terekam.
 *
 * Kolom diberi prefix `doc_ai_` (bukan `ktp_ai_`) karena dokumen yang
 * diverifikasi bisa KTP, SIM, NPWP, maupun Paspor. Nama field pada response API
 * tetap `ktp_ai_*` demi kompatibilitas dengan mobile_app yang sudah ada.
 *
 * CATATAN SEMANTIK — jangan samakan dengan `face_reason`:
 *   - `doc_ai_reason`      = NAMA RESMI dokumen ("Kartu Tanda Penduduk", ...)
 *   - `doc_ai_reason_code` = KODE DIAGNOSTIK (NO_FACE/BLURRY/WRONG_ASPECT/...)
 * `face_reason` hanya menyimpan kode, jadi jangan menyalin kolom itu tanpa
 * memisahkan kedua makna tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('doc_ai_document_type', 20)->nullable()->after('kyc_notes')
                ->comment('Tipe dokumen terdeteksi AI Core: ktp|sim|npwp|passport');
            $table->string('doc_ai_document_number', 50)->nullable()->after('doc_ai_document_type')
                ->comment('Nomor identitas hasil OCR dari dokumen');
            $table->string('doc_ai_reason')->nullable()->after('doc_ai_document_number')
                ->comment('Nama resmi dokumen (Kartu Tanda Penduduk/Surat Izin Mengemudi/Passport/Nomor Pokok Wajib Pajak)');
            $table->string('doc_ai_reason_code', 40)->nullable()->after('doc_ai_reason')
                ->comment('Kode diagnostik kegagalan: NO_FACE|NO_TEXT|WRONG_ASPECT|BLURRY|UNKNOWN_DOCUMENT');
            $table->json('doc_ai_blocking_issue')->nullable()->after('doc_ai_reason_code')
                ->comment('Daftar lengkap masalah yang memblokir validasi');
            $table->decimal('doc_ai_score', 5, 2)->nullable()->after('doc_ai_blocking_issue')
                ->comment('Skor validasi dokumen 0-100 dari AI Core');
            $table->timestamp('doc_ai_verified_at')->nullable()->after('doc_ai_score')
                ->comment('Kapan AI Core berhasil memverifikasi dokumen');
            $table->boolean('doc_ai_number_matches_profile')->nullable()->after('doc_ai_verified_at')
                ->comment('Apakah nomor hasil OCR cocok dengan nomor di profil user (null=belum dicek)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'doc_ai_document_type',
                'doc_ai_document_number',
                'doc_ai_reason',
                'doc_ai_reason_code',
                'doc_ai_blocking_issue',
                'doc_ai_score',
                'doc_ai_verified_at',
                'doc_ai_number_matches_profile',
            ]);
        });
    }
};
