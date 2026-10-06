<?php

/**
 * Penjaga atribut x-data pada modal kamera.
 *
 * x-data="{ ... }" dibungkus tanda kutip GANDA. Satu saja tanda kutip ganda
 * di dalam JavaScript-nya -- baik di string maupun di komentar -- akan
 * menutup atribut itu lebih awal. Akibatnya:
 *
 *   1. seluruh isi x-data setelah titik itu dibrowser sebagai TEKS biasa
 *      (kode JS terlihat mentah di halaman), dan
 *   2. Alpine gagal parse: "Alpine Expression Error: Invalid or unexpected
 *      token", diikuti puluhan "X is not defined" untuk semua properti
 *      (isOpen, photoTaken, mirroredView, cameras, ...).
 *
 * Bug ini sudah dua kali muncul hanya karena satu karakter di dalam komentar,
 * jadi di sini dijaga supaya tidak bisa lolos lagi.
 */

$modals = [
    'resources/views/User/components/face-scan-modal/face-scan-modal.blade.php',
    'resources/views/User/components/document-scan-modal/document-scan-modal.blade.php',
    'resources/views/User/components/document-scanner-modal/document-scanner-modal.blade.php',
    'resources/views/User/components/identity-camera-modal/identity-camera-modal.blade.php',
    'resources/views/User/components/avatar-browse-modal/avatar-browse-modal.blade.php',
];

it('tidak ada tanda kutip ganda yang membocorkan atribut x-data', function () use ($modals) {
    $problems = [];

    foreach ($modals as $rel) {
        $path = base_path($rel);

        if (! is_file($path)) {
            $problems[] = "{$rel}: file tidak ditemukan";

            continue;
        }

        $src = file_get_contents($path);

        if (! preg_match('/x-data="\{(.*?)^\s*\}"/ms', $src, $m)) {
            $problems[] = "{$rel}: atribut x-data tidak bisa dibaca";

            continue;
        }

        // Isi di antara kurung kurawal; kurung kurawal penutup sudah dipotong
        // oleh regex, jadi di sini tidak boleh ada kutip ganda sama sekali.
        if (str_contains($m[1], '"')) {
            $line = substr_count(substr($m[1], 0, strpos($m[1], '"')), "\n") + 1;
            $problems[] = "{$rel}: ada \" di dalam x-data (sekitar baris {$line}) -- gunakan tanda kutip tunggal";
        }
    }

    expect($problems)->toBe([]);
});

it('x-data modal kamera punya properti dasar yang dipakai di markup', function () use ($modals) {
    // Sengaja tidak ikut 'photoTaken': document-scanner-modal memakai
    // state 'scannerState' (pick/live/editNative/preview), bukan photoTaken.
    $required = ['isOpen', 'stream', 'stopCamera', 'mirroredView'];
    $problems = [];

    foreach ($modals as $rel) {
        $path = base_path($rel);

        if (! is_file($path)) {
            continue;
        }

        $src = file_get_contents($path);

        if (! preg_match('/x-data="\{(.*?)^\s*\}"/ms', $src, $m)) {
            continue;
        }

        foreach ($required as $prop) {
            if (! str_contains($m[1], $prop)) {
                $problems[] = basename(dirname($path)).": {$prop} tidak ada di x-data";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('kamera Kiri/Kanan memanggil selectCamera untuk kedua arah', function () use ($modals) {
    $problems = [];

    foreach ($modals as $rel) {
        $path = base_path($rel);

        if (! is_file($path)) {
            continue;
        }

        $src = file_get_contents($path);

        if (str_contains($src, 'selectCamera(cameras[')) {
            if (substr_count($src, 'selectCamera(cameras[') < 2) {
                $problems[] = basename(dirname($path)).': tombol Kiri/Kanan tidak lengkap';
            }
        }
    }

    expect($problems)->toBe([]);
});

it('face-scan-modal mengambil foto otomatis, tanpa tombol jepret', function () {
    // Dipilih eksplisit oleh pengguna: AI yang memotret dan mengarahkan,
    // jadi tidak boleh ada tombol shutter di viewfinder.
    $path = base_path('resources/views/User/components/face-scan-modal/face-scan-modal.blade.php');

    expect(is_file($path))->toBeTrue();

    $src = file_get_contents($path);

    // Tidak ada tombol yang memanggil capturePhoto secara manual.
    expect($src)->not->toContain('x-on:click="capturePhoto()"');

    // Auto-capture tetap terpasang: coach yang memanggil capturePhoto.
    expect($src)->toContain('autoCapture: true');
    expect($src)->toContain('onCapture: () => this.capturePhoto()');

    // Server verdict diterjemahkan jadi arahan lalu attempt berikutnya.
    expect($src)->toContain('reportVerdict');
    expect($src)->toContain('armAutoCapture');
});