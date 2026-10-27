<?php

/*
 * Sumber tunggal untuk huruf dan tebal teks di KETIGA panel Filament
 * (admin, user, welcome).
 *
 * Kenapa file ini ada: `Panel::font()` di tiap panel provider sebelumnya
 * ditulis sendiri-sendiri dengan nilai yang sama. Tiga salinan dari satu
 * nilai berarti ubah satu, lupa dua yang lain -- dan tidak ada tempat yang
 * bisa dibaca untuk tahu value-nya apa.
 *
 * Sekarang ketiga provider memanggil `App\Support\AppFont\AppFont`, yang
 * membaca file ini. Ubah di sini, ketiganya ikut:
 *
 *     APP_FONT_FAMILY=Georgia
 *     APP_FONT_WEIGHT=600
 *
 * Setelah mengubahnya jalankan `php artisan optimize:clear`, karena config
 * ikut ter-cache. Kalau tidak, Laravel masih memakai nilai lama yang sudah
 * di-bake ke config cache.
 *
 * CATATAN 'family' hanya boleh SATU nama keluarga, bukan stack:
 * base.blade.php Filament menulis `--font-family: '<family>'` dengan satu
 * tanda kutip, jadi stack "Google Sans Flex, Arial, sans-serif" akan terbaca
 * sebagai satu keluarga bernama NONE yang tidak ada di sistem, dan teks
 * jatuh ke fallback bawaan browser. Fallback Stack sudah ditangani
 * `public/css/app-font.css`.
 *
 * 'weight' HANYA menjadi berat bawaan teks yang tidak menetapkan beratnya
 * sendiri (paragraf, isi sel, label). Judul, tombol, dan badge tetap memakai
 * `font-weight` masing-masing -- supaya "600" tidak membuat seluruh
 * tombol ikut tebal.
 */

return [

    /*
     * Keluarga huruf. Instrument Sans -- TTF variable lokal di
     * public/fonts/Instrument_Sans/ (lihat
     * App\Support\AppFont). Nilai env hanya tersisa untuk kebutuhan khusus;
     * `AppFont::family()` sengaja DIKUNCI dan tidak membaca nilai ini lagi.
     */
    'family' => env('APP_FONT_FAMILY', 'Instrument Sans'),

    /*
     * Berat bawaan, 100 sampai 900 dalam kelipatan 100.
     * 400 = normal, 500 = medium, 600 = semibold, 700 = bold.
     */
    'weight' => (int) env('APP_FONT_WEIGHT', 400),

    /*
     * Nama brand (fallback).
     *
     * Nilai aslinya disimpan di database (tabel fm_app_settings) dan dibaca
     * lewat App\Support\AppFont\AppFont. Nilai di sini hanya dipakai kalau
     * database tidak bisa dibaca -- persis seperti family dan weight di
     * atas.
     *
     * Kenapa fallback ini penting untuk halaman error: halaman error boleh
     * muncul justru saat database tidak terjangkau. Halaman yang mencari
     * nama brand ke database bisa ikut timeout, jadi halaman error membaca
     * nilai ini (tanpa DB) supaya tidak pernah menggantung.
     */
    'brand_name' => env('APP_FONT_BRAND_NAME', 'Dekorasi Bunga Pernikahan'),

];
