<?php

namespace App\Support\IdentityVerification;

use App\Models\User\User;

/**
 * IdentityVerification — satu-satunya tempat yang boleh memberi status
 * "terverifikasi" pada sebuah akun.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * Halaman pendaftaran dan melengkapi-profil dulu menandai akun terverifikasi
 * begitu ada BERKAS selfie/foto wajah yang terunggah:
 *
 *     if (! empty($data['selfie_photo'])) {
 *         $user->forceFill(['identity_verified_at' => now()])->save();
 *     }
 *
 * Itu berarti siapa pun bisa mengunggah foto apa saja — bahkan gambar yang
 * sama sekali bukan wajah — dan langsung tercatat sebagai identitas
 * terverifikasi. Verifikasi wajah
 * Requires perbandingan embedding, bukan keberadaan file.
 *
 * ATURAN SEKARANG
 * ---------------
 *   identity_verified_at  -> hanya diisi oleh verifyFaceAi() yang memanggil
 *                            markVerified() setelah FaceNet benar-benar
 *                            mengembalikan similarity di atas threshold.
 *   liveness_completed    -> hanya true setelah AI selesai memeriksa
 *                            liveness_checks. Kehadiran berkas TIDAK cukup.
 *
 * Status "sudah mengunggah foto" tetap bisa ditampilkan, tapi itu urusan
 * UI (User::hasSelfiePhoto()), bukan status keamanan.
 */
class IdentityVerification
{
    /**
     * Tandai identitas terverifikasi. HANYA dipanggil setelah AI benar-benar
     * memverifikasi wajah terhadap dokumen.
     *
     * @param  array<string, mixed>  $result  hasil FaceService::verifyFace()
     */
    public function markVerified(User $user, array $result): void
    {
        $success = (bool) ($result['success'] ?? false);
        $verified = $success && (bool) ($result['verified'] ?? false);

        // Tanpa konfirmasi bahwa AI benar-benar selesai, tidak ada status yang
        // boleh naik. Sebelumnya `liveness_completed` ditulis `true` tanpa syarat,
        // sehingga respons FaceNet yang GAGAL (success=false), exception, atau
        // array kosong tetap menandai pengguna sebagai lolos liveness -- padahal
        // tidak ada pemeriksaan wajah yang pernah terjadi. Sekarang keduanya
        // turun ke false dulu, baru dinaikkan hanya bila memang success.
        $user->forceFill([
            'liveness_completed' => $success,
        ]);

        if ($verified) {
            $user->identity_verified_at = now();
        }

        $user->save();
    }

    /**
     * Reset status verifikasi, mis. setelah pengguna mengganti dokumen.
     *
     * Wajib dipanggil setiap kali foto identitas berubah: foto lama sudah tidak
     * relevan, jadi verifikasi lama tidak boleh ikut terbawa.
     */
    public function markUnverified(User $user): void
    {
        $user->forceFill([
            'identity_verified_at' => null,
            'liveness_completed' => false,
            'face_verified_at' => null,
        ])->save();
    }
}