<?php

namespace App\Support\ReportCategory;

/**
 * Label humans untuk kategori laporan.
 *
 * Nilai mentah di kolom `reports.category` adalah slug teknis
 * (`general_question`, `bug_report`). Nilai itu boleh muncul di form dan
 * di database, tapi TIDAK boleh muncul di dokumen resmi -- reader tidak
 * tahu apa arti "general_question".
 *
 * Daftar ini dulu disalin di beberapa controller, jadi setiap tempat bisa
 * berbeda. Sekarang satu sumber.
 */
class ReportCategory
{
    /**
     * Slug -> label bahasa manusia.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'bug_report' => 'Lapor Bug',
        'account_issue' => 'Masalah Akun',
        'order_help' => 'Bantuan Pesanan',
        'payment_issue' => 'Masalah Pembayaran',
        'decor_consultation' => 'Konsultasi Dekorasi',
        'general_question' => 'Pertanyaan Umum',
    ];

    /**
     * Label humans untuk sebuah kategori.
     *
     * Kategori yang tidak dikenal (mis. slug baru yang belum masuk daftar)
     * tetap ditampilkan rapi: underscore diganti spasi lalu dikapitalisasi.
     * Jadi tidak pernah ada "_" yang bocor ke dokumen.
     */
    public static function label(?string $category): string
    {
        if ($category === null || trim($category) === '') {
            return '-';
        }

        $category = trim($category);

        if (isset(self::LABELS[$category])) {
            return self::LABELS[$category];
        }

        $words = preg_split('/[_\-\s]+/', $category, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)).mb_strtolower(mb_substr($word, 1)),
            $words,
        )) ?: '-';
    }
}
