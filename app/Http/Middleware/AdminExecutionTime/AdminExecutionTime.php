<?php

namespace App\Http\Middleware\AdminExecutionTime;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batas waktu request panel admin.
 *
 * Panel admin sengaja TANPA pagination (permintaan pemilik: semua baris
 * ditampilkan). Halaman "Studio API & Pengembang" merender 611 baris
 * sekaligus; di mesin ini satu render penuh butuh ~13 detik, dan request
 * PERTAMA sesudah `php artisan optimize:clear` (semua view dikompilasi ulang)
 * bisa melewati batas bawaan 30 detik -- halaman berubah menjadi
 * "Terjadi Kesalahan Server" tanpa ada yang salah di kodenya.
 *
 * Batasnya dinaikkan hanya untuk request panel admin dan update Livewire
 * (tabel dirender lewat `/livewire/update`, bukan `/admin/...`), bukan untuk
 * seluruh aplikasi. Request biasa tetap selesai dalam milidetik, jadi angka
 * ini tidak memperlambat apa pun -- ia hanya mencegah fatal error saat
 * kompilasi view + render 611 baris terjadi bersamaan.
 */
class AdminExecutionTime
{
    /**
     * Detik. Cukup untuk render dingin (~29 detik) dengan ruang lega.
     */
    private const SECONDS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin*', 'livewire/*')) {
            return $next($request);
        }

        // Simpan nilai lama dan KEMBALIKAN sesudah response.
        //
        // `set_time_limit()` itu proses-wide, bukan per-request. Di suite
        // test (satu proses PHPUnit menjalankan ratusan test) membiarkannya
        // 120 detik membuat SELURUH suite mati di tengah jalan -- test yang
        // tadinya lulus berubah jadi "Maximum execution time of 120 seconds
        // exceeded". Mengembalikan nilai lama juga membuat Octane/queue
        // worker tidak mewarisi batas ini.
        $previous = (int) ini_get('max_execution_time');

        @set_time_limit(self::SECONDS);

        try {
            return $next($request);
        } finally {
            @set_time_limit($previous);
        }
    }
}
