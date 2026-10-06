<?php

use App\Http\Controllers\Api\User\ProfileController\ProfileController;
use App\Models\User\User;

/*
 * Menutup logic persistence hasil /api/ktp/verify.
 *
 * Sebelum migrasi 2026_10_05_000001 tidak ada satu pun kolom untuk hasil
 * verifikasi dokumen: uploadKtp() hanya menyimpan path foto. Akibatnya penolakan
 * AI tidak meninggalkan jejak, kyc_status tidak pernah direset saat dokumen
 * diganti, dan nomor hasil OCR tidak pernah dibandingkan dengan profil.
 *
 * Diuji via reflection karena kedua method memang private — yang penting adalah
 * nilai kolom yang dihasilkan, bukan cara dipanggil.
 */
describe('documentVerificationUpdate()', function () {
    /** @return array<string, mixed> */
    function callDocumentUpdate(User $user, array $ai): array
    {
        $controller = new ProfileController;
        $method = new ReflectionMethod($controller, 'documentVerificationUpdate');
        $method->setAccessible(true);

        return $method->invoke($controller, $user, $ai);
    }

    test('menyimpan nama dokumen dan kode diagnostik pada kolom terpisah', function () {
        $update = callDocumentUpdate(new User, [
            'success' => true,
            'verified' => false,
            'reason' => 'Kartu Tanda Penduduk',
            'reason_code' => 'BLURRY',
            'blocking_issue' => ['BLURRY'],
            'document_type' => 'ktp',
            'document_number' => '3171011203030004',
            'score' => 61.5,
        ]);

        // Nama dokumen dan kode error TIDAK boleh tercampur di satu kolom.
        expect($update['doc_ai_reason'])->toBe('Kartu Tanda Penduduk');
        expect($update['doc_ai_reason_code'])->toBe('BLURRY');
        expect($update['doc_ai_blocking_issue'])->toBe(['BLURRY']);
        expect($update['doc_ai_document_type'])->toBe('ktp');
        expect($update['doc_ai_document_number'])->toBe('3171011203030004');
        expect((float) $update['doc_ai_score'])->toBe(61.5);
    });

    test('reason_code null saat tidak ada masalah, verified_at terisi saat lolos', function () {
        $update = callDocumentUpdate(new User, [
            'success' => true,
            'verified' => true,
            'reason' => 'Surat Izin Mengemudi',
            'reason_code' => null,
            'blocking_issue' => null,
            'document_type' => 'sim',
            'score' => 88.0,
        ]);

        expect($update['doc_ai_reason_code'])->toBeNull();
        expect($update['doc_ai_blocking_issue'])->toBeNull();
        expect($update['doc_ai_verified_at'])->not->toBeNull();
    });

    test('verified_at null saat gagal meski AI tetap sukses', function () {
        $update = callDocumentUpdate(new User, [
            'success' => true,
            'verified' => false,
            'reason' => 'Passport',
            'reason_code' => 'WRONG_ASPECT',
        ]);

        expect($update['doc_ai_verified_at'])->toBeNull();
    });

    test('mereset kyc_status agar admin me-review ulang', function () {
        $user = new User;
        $user->kyc_status = 'verified';

        $update = callDocumentUpdate($user, [
            'success' => true,
            'verified' => true,
            'reason' => 'Nomor Pokok Wajib Pajak',
            'document_type' => 'npwp',
        ]);

        // Ini bug lama: upload ulang KTP dulu tidak mereset kyc_status sama sekali.
        expect($update)->toHaveKey('kyc_status');
        expect($update['kyc_status'])->toBeNull();
    });

    test('tidak menyentuh kolom apa pun saat layanan AI gagal', function () {
        // Menulis null di sini akan menghapus bukti hasil scan sebelumnya.
        $update = callDocumentUpdate(new User, [
            'success' => false,
            'error' => true,
            'reason' => 'AI_UNAVAILABLE',
        ]);

        expect($update)->toBe([]);
    });
});

describe('documentNumberMatchesProfile()', function () {
    function callNumberMatch(User $user, array $ai): ?bool
    {
        $controller = new ProfileController;
        $method = new ReflectionMethod($controller, 'documentNumberMatchesProfile');
        $method->setAccessible(true);

        return $method->invoke($controller, $user, $ai);
    }

    test('cocok untuk KTP dengan format identik', function () {
        $user = new User(['ktp_number' => '3171011203030004']);

        expect(callNumberMatch($user, [
            'document_type' => 'ktp',
            'document_number' => '3171011203030004',
        ]))->toBeTrue();
    });

    test('cocok walau format cetak berbeda', function () {
        // NPWP dicetak bergaris, NIK bisa dipisah spasi.
        $user = new User(['npwp_number' => '09.254.294.3-407.000']);

        expect(callNumberMatch($user, [
            'document_type' => 'npwp',
            'document_number' => '092542943407000',
        ]))->toBeTrue();
    });

    test('tidak cocok bila nomornya berbeda', function () {
        $user = new User(['ktp_number' => '3171011203030004']);

        expect(callNumberMatch($user, [
            'document_type' => 'ktp',
            'document_number' => '3271011203030004',
        ]))->toBeFalse();
    });

    test('null saat nomor tidak terbaca, bukan false', function () {
        // Tidak terbaca != tidak cocok. Membedakan keduanya penting agar
        // kegagalan OCR tidak salah dicatat sebagai manipulasi identitas.
        expect(callNumberMatch(new User(['ktp_number' => '3171011203030004']), [
            'document_type' => 'ktp',
            'document_number' => null,
        ]))->toBeNull();

        expect(callNumberMatch(new User(['ktp_number' => '3171011203030004']), [
            'document_type' => 'ktp',
            'document_number' => '  ',
        ]))->toBeNull();
    });

    test('null saat profil belum punya nomor untuk tipe tersebut', function () {
        expect(callNumberMatch(new User(['ktp_number' => '3171011203030004']), [
            'document_type' => 'passport',
            'document_number' => 'C1234567',
        ]))->toBeNull();
    });

    test('memakai kolom nomor sesuai tipe dokumen', function () {
        $user = new User([
            'ktp_number' => '3171011203030004',
            'sim_number' => '123456789012',
            'npwp_number' => '092542943407000',
            'passport_number' => 'C1234567',
        ]);

        expect(callNumberMatch($user, ['document_type' => 'sim', 'document_number' => '123456789012']))->toBeTrue();
        expect(callNumberMatch($user, ['document_type' => 'npwp', 'document_number' => '092542943407000']))->toBeTrue();
        expect(callNumberMatch($user, ['document_type' => 'passport', 'document_number' => 'C1234567']))->toBeTrue();

        /* Huruf pembeda pada nomor paspor wajib dipertahankan. Kalau keduanya
         * dinormalisasi ke digit saja, "C1234567" dan "1234567" terbaca sama
         * padahal itu dua dokumen berbeda. */
        expect(callNumberMatch($user, ['document_type' => 'passport', 'document_number' => '1234567']))->toBeFalse();
        expect(callNumberMatch($user, ['document_type' => 'passport', 'document_number' => 'D1234567']))->toBeFalse();

        // Casing berbeda tetap dokumen yang sama.
        expect(callNumberMatch($user, ['document_type' => 'passport', 'document_number' => 'c1234567']))->toBeTrue();
    });
});
