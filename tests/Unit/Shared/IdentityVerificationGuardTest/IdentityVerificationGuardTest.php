<?php

/**
 * Penjaga: identitas hanya boleh "terverifikasi" setelah AI benar-benar
 * memverifikasi wajah terhadap dokumen.
 *
 * Bug yang dicegah
 * ----------------
 * Dulu halaman pendaftaran dan melengkapi-profil menandai akun terverifikasi
 * begitu ada berkas selfie/foto wajah yang terunggah:
 *
 *     if (! empty($data['selfie_photo'])) {
 *         $user->forceFill(['identity_verified_at' => now()])->save();
 *     }
 *
 * Akibatnya siapa pun bisa melewati verifikasi identitas hanya dengan
 * mengunggah gambar apa pun. Berkas yang ADA bukan bukti kecocokan wajah --
 * yang membuktikan adalah perbandingan embedding FaceNet di AI Core.
 *
 * Dicek di sini dengan membaca kode sumber, bukan hanya status saat ini,
 * supaya hole tidak bisa kembali diam-diam lewat refactor.
 */

/** @return array<int, string> file yang boleh menyentuh status verifikasi */
function verificationOwners(): array
{
    return [
        base_path('app/Support/IdentityVerification/IdentityVerification.php'),
        // verifyFaceAi() memang memanggil markVerified(), jadi file ini
        // tidak dilarang -- ia hanya tidak boleh memberi nilai langsung.
        base_path('app/Livewire/User/CompleteProfileComponent/CompleteProfileComponent.php'),
        base_path('app/Livewire/Welcome/CompleteProfileComponent/CompleteProfileComponent.php'),
        base_path('app/Http/Controllers/Api/User/ProfileController/ProfileController.php'),
    ];
}

/**
 * Pola berbahaya: mengisi timestamp verifikasi di dalam pengujian
 * keberadaan berkas.
 */
it('tidak ada kode yang menandai verified hanya karena berkas foto ada', function () {
    /*
     * Versi pertama test ini memakai regex
     *
     *     if\s*\(!\s*empty\s*\(\$data\['...'\]\)\s*\)\s*\{[^}]*identity_verified_at
     *
     * dan LULUS untuk kode yang justru berisi bug tersebut. Alasannya:
     * $user->forceFill([...])->save(); memakai tanda kurung, dan [^}]* tidak
     * pernah melewati ')' -- jadi cabang if berisi assignment tidak pernah
     * cocok, apa pun isinya. Guard yang tidak menangkap bug yang dituliskan
     * untuknya lebih buruk daripada tidak ada guard sama sekali.
     *
     * Sekarang diperiksa secara struktural: kurung kurawal dihitung per
     * statement, lalu dicek apakah ada penulisan status verifikasi di dalam
     * cabang yang syaratnya hanya mengecek keberadaan file foto.
     */
    $photoFields = ['selfie_photo', 'face_scan_photo', 'ktp_photo'];
    $statusFields = ['identity_verified_at', 'liveness_completed', 'face_verified_at'];

    $offenders = [];

    foreach (verificationOwners() as $file) {
        /*
         * Komentar dibuang dari SELURUH file dulu, baru dipecah per baris.
         *
         * IdentityVerification.php memuat contoh kode yang justru rusak di
         * dalam docblock-nya, sebagai dokumentasi bug yang diperbaiki. Kalau
         * comment stripping dilakukan per baris, blok /* ... *\/ yang
         *.multiline tidak akan terhapus dan guard akan menandai file itu
         * sendiri -- sehingga contoh paling berharga justru jadi racun.
         */
        $code = file_get_contents($file);
        $code = preg_replace('#/\*.*?\*/#s', '', $code);

        $lines = explode("\n", str_replace("\r\n", "\n", $code));
        $count = count($lines);

        foreach ($lines as $k => $raw) {
            $lines[$k] = preg_replace('#//.*$#', '', $raw);
        }

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Condition must mention a photo field.
            $mentionsPhoto = false;
            foreach ($photoFields as $field) {
                if (str_contains($line, $field)) {
                    $mentionsPhoto = true;
                    break;
                }
            }

            if (! $mentionsPhoto) {
                continue;
            }

            // Only a guard branch: `if (... !empty(...) ...)` with a `{`.
            if (! str_contains($line, 'if') || ! str_contains($line, '{')) {
                continue;
            }

            /*
             * The body may sit on the SAME line as the condition:
             *
             *     if (! empty($data['selfie_photo'])) { $user->forceFill(...)->save(); }
             *
             * Reading only the following lines therefore sees an empty body and
             * reports nothing. The inline tail after the last ')' is part of the
             * branch too, so it must be included.
             */
            $bracePos = strpos($line, '{');
            $body = substr($line, $bracePos + 1);

            // And it may continue on the following lines.
            $depth = substr_count(substr($line, $bracePos), '{')
                - substr_count(substr($line, $bracePos), '}');

            for ($j = $i + 1; $j < $count && $depth > 0; $j++) {
                $code = preg_replace('#//.*$#', '', $lines[$j]);
                $depth += substr_count($code, '{') - substr_count($code, '}');
                $body .= "\n".$lines[$j];
            }

            foreach ($statusFields as $field) {
                if (str_contains($body, $field)) {
                    $offenders[] = sprintf(
                        '%s:%d cabang "empty foto" menulis %s',
                        basename($file),
                        $i + 1,
                        $field
                    );
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('IdentityVerification adalah satu-satunya penemu status verifikasi', function () {
    /* Setiap kolom verifikasi diisi harus lewat service, supaya ada satu
       tempat untuk diaudit -- bukan kondisi terpisah di beberapa file. */
    $service = base_path('app/Support/IdentityVerification/IdentityVerification.php');

    expect(is_file($service))->toBeTrue();

    $src = file_get_contents($service);

    expect($src)->toContain('markVerified');
    expect($src)->toContain('markUnverified');

    // Di service sendiri, penulisan hanya boleh di dalam kondisi $verified.
    expect($src)->toMatch('/if\s*\(\s*\$verified\s*\)\s*\{[^}]*identity_verified_at/s');
});

it('liveness_completed hanya true ketika AI benar-benar mengembalikan success', function () {
    $problems = [];

    foreach ([
        base_path('app/Livewire/User/CompleteProfileComponent/CompleteProfileComponent.php'),
        base_path('app/Livewire/Welcome/CompleteProfileComponent/CompleteProfileComponent.php'),
    ] as $file) {
        $src = file_get_contents($file);

        // Bentuk berbahaya: hardcode true tanpa bergantung hasil AI.
        if (preg_match("/'liveness_completed'\s*=>\s*true\s*\]/", $src)) {
            $problems[] = basename($file).": liveness_completed di-set true tanpa bergantung hasil AI";
        }

        // Bentuk yang benar: mengikuti flag success dari AI.
        if (! str_contains($src, "'liveness_completed' => (bool) (\$ai['success'] ?? false)")) {
            $problems[] = basename($file).": liveness_completed tidak mengikuti \$ai['success']";
        }
    }

    expect($problems)->toBe([]);
});

it('verifyFaceAi tetap memberi status verified saat AI benar-benar cocok', function () {
    /* Yang dijaga di atas boleh tightened, tapi JANGAN sampaiverifyFaceAi
       berhenti memberi status -- tanpa itu verifikasi tidak pernah mungkin
       selesai dan user terkunci selamanya. */
    foreach ([
        base_path('app/Livewire/User/CompleteProfileComponent/CompleteProfileComponent.php'),
        base_path('app/Livewire/Welcome/CompleteProfileComponent/CompleteProfileComponent.php'),
    ] as $file) {
        $src = file_get_contents($file);

        expect($src)->toContain('markVerified')
            ->and($src)->toContain('FaceService::class')
            ->and($src)->toContain('verifyFace');
    }
});