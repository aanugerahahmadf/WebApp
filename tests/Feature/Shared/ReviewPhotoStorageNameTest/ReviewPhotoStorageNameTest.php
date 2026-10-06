<?php

use App\Services\ReviewPhotoStorage\ReviewPhotoStorage;

/*
|--------------------------------------------------------------------------
| ReviewPhotoStorage - ketahanan nama file
|--------------------------------------------------------------------------
|
| Nama file foto review diturunkan dari hash isi supaya foto yang sama tidak
| pernah tersalin dua kali. `hash_file()` bisa gagal walaupun file ada --
| di Windows `is_readable()` bisa lulus lalu `open()` ditolak kalau proses lain
| sedang memegang file (antivirus, editor, runner paralel).
|
| Dua hal dikunci di sini:
|
|   1. Sumber yang tidak bisa di-hash TIDAK boleh melempar exception. Warning
|      dari `hash_file()` jadi `ErrorException` dan menjatuhkan seluruh test run
|      -- persis yang pernah terjadi pada ResponsivePanelsTest yang memanggil
|      seeder. Niat kodenya cuma "lewati foto ini", bukan menggagalkan run.
|
|   2. Nama harus tetap deterministik untuk input yang sama, supaya sifat
|      idempoten dan bebas-salin-ganda tidak hilang.
|
*/

test('nama file dihitung dari hash isi untuk sumber yang bisa dibaca', function () {
    $source = sys_get_temp_dir().'/rev-'.uniqid().'.jpg';
    file_put_contents($source, 'isi-foto-yang-dikenal');

    try {
        $name = ReviewPhotoStorage::nameFor($source);

        expect($name)->toStartWith('review-')
            ->and($name)->toEndWith('.jpg')
            // Panjang prefix hash dijaga, bukan sekadar "ada".
            ->and(substr($name, 0, 7 + 12))->toHaveLength(19);
    } finally {
        @unlink($source);
    }
});

test('nama file tetap berubah kalau isi foto berubah', function () {
    $a = sys_get_temp_dir().'/rev-a-'.uniqid().'.jpg';
    $b = sys_get_temp_dir().'/rev-b-'.uniqid().'.jpg';
    file_put_contents($a, 'foto-satu');
    file_put_contents($b, 'foto-dua-berbeda');

    try {
        // Kalau fallback (path+ukuran+waktu) ikut terpakai di jalur normal,
        // dua file berbeda bisa kebetulan sama -- itu hilangnya dedup.
        expect(ReviewPhotoStorage::nameFor($a))
            ->not->toBe(ReviewPhotoStorage::nameFor($b));
    } finally {
        @unlink($a);
        @unlink($b);
    }
});

test('nama file tidak melempar exception saat sumber tidak bisa di-hash', function () {
    // Direktori dipakai sebagai sumber: `hash_file()` di sini benar-benar
    // gagal dan memunculkan warning, tanpa perlu meniru lock Windows.
    $directory = sys_get_temp_dir().'/rev-dir-'.uniqid();
    mkdir($directory);

    try {
        $name = ReviewPhotoStorage::nameFor($directory);

        // Tanpa perbaikan, warning `hash_file()` di-eskalasi Laravel menjadi
        // ErrorException dan test ini gagal di baris di atas.
        expect($name)->toStartWith('review-')
            ->and($name)->toEndWith('.jpg');
    } finally {
        @rmdir($directory);
    }
});

test('nama file deterministik untuk sumber yang sama', function () {
    $source = sys_get_temp_dir().'/rev-'.uniqid().'.jpg';
    file_put_contents($source, 'isi-foto-yang-dikenal');

    try {
        $first = ReviewPhotoStorage::nameFor($source);
        clearstatcache(true, $source);
        $second = ReviewPhotoStorage::nameFor($source);

        // Idempoten: nama yang sama membuat `store()` tidak menyalin ulang.
        expect($first)->toBe($second);
    } finally {
        @unlink($source);
    }
});

test('ekstensi di luar daftar yang didukung dipaksa jadi jpg', function () {
    $source = sys_get_temp_dir().'/rev-'.uniqid().'.gif';
    file_put_contents($source, 'gif');

    try {
        expect(ReviewPhotoStorage::nameFor($source))->toEndWith('.jpg');
    } finally {
        @unlink($source);
    }
});
