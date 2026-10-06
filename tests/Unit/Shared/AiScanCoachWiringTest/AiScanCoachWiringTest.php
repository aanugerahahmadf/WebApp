<?php

/**
 * Penjaga pemasangan AI Scan Coach di seluruh modal kamera.
 *
 * Coach memberi arahan real-time (fokus, pencahayaan, posisi subjek) lewat suara
 * dan visual. Dua kelas bug yang paling merusak di sini:
 *
 *  1. Kutip ganda di dalam x-data="{...}" memutus atribut HTML lebih awal.
 *     Akibatnya Alpine gagal parse dan SETIAP properti dilaporkan "not defined",
 *     sehingga modal ikut mati -- bukan hanya fiturnya.
 *
 *  2. Coach yang dibuat tapi tidak pernah dihentikan. Loop requestAnimationFrame
 *     dan suara speechSynthesis akan terus berjalan setelah modal ditutup:
 *     baterai terkuras, dan suara bisa bocor ke modal berikutnya.
 *
 * Modul JS-nya sendiri punya test sendiri (node --test
 * resources/js/ai-scan-coach/). Job `ai-scan-coach` di ci.yml yang memanggilnya.
 * Test ini hanya menjaga pemasangan di Blade.
 */

/** @return array<string, array{0: string, 1: string}> label => [path, videoRef] */
function cameraModals(): array
{
    $out = [];

    foreach (['User', 'Welcome'] as $area) {
        $specs = [
            'face-scan-modal' => 'camVideo',
            'document-scan-modal' => 'camVideo',
            'identity-camera-modal' => 'camVideo',
            'document-scanner-modal' => 'scanVideo',
        ];

        foreach ($specs as $modal => $videoRef) {
            $out["{$area}/{$modal}"] = [
                "resources/views/{$area}/components/{$modal}/{$modal}.blade.php",
                $videoRef,
            ];
        }
    }

    return $out;
}

it('tidak ada tanda kutip ganda yang membocorkan atribut x-data di modal kamera', function () {
    $problems = [];

    foreach (cameraModals() as $label => [$rel, $_]) {
        $path = base_path($rel);

        if (! is_file($path)) {
            $problems[] = "{$label}: berkas tidak ditemukan";

            continue;
        }

        $src = file_get_contents($path);

        if (! preg_match('/x-data="\{(.*?)^\s*\}"/ms', $src, $m)) {
            $problems[] = "{$label}: atribut x-data tidak terbaca";

            continue;
        }

        if (str_contains($m[1], '"')) {
            $at = substr_count(substr($m[1], 0, strpos($m[1], '"')), "\n") + 1;
            $problems[] = "{$label}: ada \" di dalam x-data (sekitar baris {$at}) -- pakai kutip tunggal";
        }
    }

    expect($problems)->toBe([]);
});

it('setiap modal kamera memasang AI scan coach', function () {
    $problems = [];

    foreach (cameraModals() as $label => [$rel, $_]) {
        $path = base_path($rel);

        if (! is_file($path)) {
            continue;
        }

        $src = file_get_contents($path);

        foreach ([
            'AIScanCoach' => 'memakai window.AIScanCoach',
            'startCoach()' => 'metode startCoach()',
            'stopCoach()' => 'metode stopCoach()',
            'this.coach = factory({' => ' pembuatan coach',
        ] as $needle => $desc) {
            if (! str_contains($src, $needle)) {
                $problems[] = "{$label}: tidak {$desc}";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('coach punya titik mount dan sumber video yang benar', function () {
    $problems = [];

    foreach (cameraModals() as $label => [$rel, $videoRef]) {
        $path = base_path($rel);

        if (! is_file($path)) {
            continue;
        }

        $src = file_get_contents($path);

        if (! str_contains($src, 'x-ref="camWrap"') && ! str_contains($src, 'x-ref="scanWrap"')) {
            $problems[] = "{$label}: container kamera tidak punya x-ref untuk mount overlay";
        }

        if (! str_contains($src, "video: () => this.\$refs.{$videoRef}")) {
            $problems[] = "{$label}: coach tidak diarahkan ke \$refs.{$videoRef}";
        }
    }

    expect($problems)->toBe([]);
});

it('coach dihentikan tepat saat stream kamera dimatikan', function () {
    $problems = [];

    foreach (cameraModals() as $label => [$rel, $_]) {
        $path = base_path($rel);

        if (! is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        /* Bodies dihitung dengan menghitung kurung kurawal, bukan regex
         * non-greedy. stopCamera() punya if di dalamnya, jadi pola
         * `.*?^\s*\}` akan berhenti di penutup if -- persis di atas yang membuat
         * versi sebelumnya salah baca semua berkas.
         *
         * Satu modal bisa punya lebih dari satu stopCamera() (mis. satu untuk
         * ganti kamera, satu untuk menutup), jadi setiap body diperiksa. */
        $bodies = [];
        $total = 0;

        for ($i = 0; $i < count($lines); $i++) {
            if (! preg_match('/^\s*stopCamera\(\)\s*\{\s*$/', $lines[$i])) {
                continue;
            }

            $total++;
            $depth = 1;
            $body = '';

            for ($j = $i + 1; $j < count($lines); $j++) {
                $body .= $lines[$j]."\n";

                // Kurung dihitung per baris setelah komentar dibuang, supaya
                // kurung di dalam penjelasan tidak mengacaukan hitungan.
                $code = preg_replace('#/\*.*?\*/#s', '', $lines[$j]);
                $code = preg_replace('#//.*$#', '', $code);
                $depth += substr_count($code, '{') - substr_count($code, '}');

                if ($depth <= 0) {
                    break;
                }
            }

            $bodies[] = $body;
        }

        if ($total === 0) {
            $problems[] = "{$label}: stopCamera() tidak ditemukan";

            continue;
        }

        foreach ($bodies as $index => $body) {
            if (! str_contains($body, 'this.stopCoach()')) {
                $problems[] = "{$label}: stopCamera() #{$index} tidak memanggil stopCoach()";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('modul AI scan coach terdaftar sebagai entry point Vite dan diimpor platform entry', function () {
    $vite = base_path('vite.config.js');

    if (! is_file($vite)) {
        $this->fail('vite.config.js tidak ditemukan');
    }

    $src = file_get_contents($vite);

    expect($src)->toContain('resources/js/ai-scan-coach/ai-scan-coach.js');

    // Modul harus masuk lewat entry point platform supaya window.AIScanCoach
    // selalu ada, bukan hanya ketika ada yang memanggil @vite.
    foreach (['app-web', 'app-mobile', 'app-desktop'] as $entry) {
        $path = base_path("resources/js/{$entry}/{$entry}.js");

        if (! is_file($path)) {
            continue;
        }

        expect(str_contains(file_get_contents($path), 'ai-scan-coach'))->toBeTrue();
    }
});

it('overlay coach tidak merender tombol apa pun', function () {
    // Arahan harus otomatis begitu kamera terbuka. Tombol mute atau toggle
    // melanggar itu, dan tidak ada tempat menyembunyikannya di viewfinder.
    $path = base_path('resources/js/ai-scan-coach/ai-scan-coach.js');

    if (! is_file($path)) {
        $this->fail('modul ai-scan-coach tidak ditemukan');
    }

    $src = file_get_contents($path);

    expect($src)->not->toContain('createElement(\'button\')');
    expect($src)->not->toContain('aisc__mute');
});

it('semua empat modal kamera memberi tahu coach jenis dokumen yang dipindai', function () {
    // KTP, NPWP, SIM, dan Paspor punya ukuran fisik berbeda. Kalau bingkai dan
    // kalimat AI tidak ikut berubah, KTP yang dipegang benar tetap akan
    // dilaporkan "terlalu jauh" hanya karena bentuk bingkainya keliru.
    $documentModals = [
        'resources/views/User/components/document-scanner-modal/document-scanner-modal.blade.php',
        'resources/views/User/components/document-scan-modal/document-scan-modal.blade.php',
    ];

    foreach ($documentModals as $rel) {
        $src = file_get_contents(base_path($rel));

        /* Collect first, assert once.
         *
         * expect($src)->toContain($needle, $message) does NOT do what it looks
         * like in Pest: the second argument is not a failure message, so a real
         * problem fails with a wall of Blade source dumped as "Expected" and the
         * needle nowhere in sight. Collecting the missing pieces keeps the report
         * readable. */
        $missing = [];

        foreach ([
            'docType:' => 'tidak mengirim docType ke coach',
            'autoCapture: true' => 'tidak memakai auto-capture',
            'announceDocument(' => 'tidak mengumumkan jenis dokumen',
            'identity_type' => 'tidak membaca identity_type dari form',
        ] as $needle => $desc) {
            if (! str_contains($src, $needle)) {
                $missing[] = "{$rel}: {$desc} (tidak menemukan '{$needle}')";
            }
        }

        expect($missing)->toBe([]);
    }
});

it('modul coach memuat keempat geometry kartu', function () {
    $path = base_path('resources/js/ai-scan-coach/ai-scan-coach.js');

    expect(is_file($path))->toBeTrue();

    $src = file_get_contents($path);

    // Nilai ini persis yang tersimpan di users.identity_type.
    foreach (['ktp', 'npwp', 'sim', 'passport'] as $type) {
        expect($src)->toContain($type.':');
    }

    expect($src)->toContain('docGuideFor');
    expect($src)->toContain('resolveDocType');
});

it('face-scan-modal tidak punya tombol jepret dan memakai auto-capture', function () {
    // Diminta eksplisit: yang di layar harus otomatis, bukan ada tombol
    // bulat di bawah viewfinder.
    $path = base_path('resources/views/User/components/face-scan-modal/face-scan-modal.blade.php');

    expect(is_file($path))->toBeTrue();

    $src = file_get_contents($path);

    expect($src)->not->toContain('heroicon-m-camera');
    expect($src)->not->toMatch('/x-on:click="capturePhoto\(\)"/');

    // Autopanoe + percobaan terbatas supaya tidak loops tanpa henti.
    expect($src)->toContain('autoCapture: true');
    expect($src)->toContain('reportVerdict');
});
