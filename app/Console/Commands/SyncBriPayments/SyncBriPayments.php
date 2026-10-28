<?php

namespace App\Console\Commands\SyncBriPayments;

use App\Enums\PaymentStatus\PaymentStatus;
use App\Models\Transaction\Transaction;
use App\Services\BriService\BriService;
use Illuminate\Console\Command;

/**
 * Jalur cadangan untuk melunasi pembayaran BRI.
 *
 * Webhook adalah jalur utama, tetapi notifikasi bisa hilang (server mati,
 * URL belum terdaftar, signature beda). Command ini membaca mutasi rekening
 * settlement dan mencocokkan kredit dengan transaksi yang masih pending:
 *
 *   1. Nomor Virtual Account yang tercantum di `remark` mutasi (paling tepat,
 *      karena nomor VA unik per user + pesanan), lalu
 *   2. nominal yang sama (untuk QRIS statis / transfer manual).
 *
 * Dijadwalkan tiap 5 menit (routes/console/console.php), jadi pembayaran
 * tetap otomatis LUNAS walau webhook tidak sampai.
 */
class SyncBriPayments extends Command
{
    protected $signature = 'app:sync-bri-payments
        {--dry-run : Tampilkan hasil tanpa menandai LUNAS}';

    protected $description = 'Cek mutasi BRI dan otomatis tandai pembayaran (VA/QRIS/transfer) sebagai LUNAS';

    public function handle(BriService $bri): int
    {
        if (! $bri->enabled()) {
            $this->warn('BRI API tidak aktif. Set BRI_ENABLED=true dan isi kredensial.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('(dry-run) Tidak ada perubahan status yang akan disimpan.');
        }

        $lookbackDays = config('bri.lookback_days', 7);
        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays($lookbackDays)->format('Y-m-d');

        $this->line("Mengambil mutasi BRI {$startDate} sd {$endDate} ...");

        $rows = $bri->statement($startDate, $endDate, config('bri.max_rows', 200));
        if (empty($rows)) {
            $this->warn('Mutasi kosong / gagal ditarik. Pastikan kredensial & rekening benar.');

            return self::FAILURE;
        }

        $this->line('Baris mutasi yang didapat: '.count($rows));

        // Semua transaksi order yang masih menunggu, termasuk VA (bri_va) dan
        // QRIS dinamis. Jangan menyaring `payment_gateway = manual` saja:
        // transaksi VA/QRIS justru yang paling butuh rekonsiliasi.
        $pending = Transaction::where('type', 'order')
            ->whereIn('status', ['pending', 'processing'])
            ->with('order')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Tidak ada pembayaran yang menunggu.');

            return self::SUCCESS;
        }

        $tolerance = config('bri.amount_tolerance', 500);
        $matched = 0;

        foreach ($rows as $row) {
            // Hanya kredit (uang masuk). Debit = uang keluar, dilewati.
            $credit = (float) ($row['creditAmount'] ?? 0);
            $typeAmount = strtoupper((string) ($row['typeAmount'] ?? ''));
            if ($credit <= 0 || ($typeAmount !== '' && $typeAmount !== 'CREDIT')) {
                continue;
            }

            $remark = (string) ($row['remark'] ?? $row['description'] ?? '');
            $normalizedRemark = strtoupper((string) preg_replace('/\s+/', '', $remark));

            $pointer = null;

            // 1. Cocokkan nomor Virtual Account di keterangan mutasi. Nomor VA
            //    unik per user + pesanan, jadi ini identitas paling kuat.
            if ($normalizedRemark !== '') {
                $pointer = $pending->first(function (Transaction $tx) use ($normalizedRemark): bool {
                    $va = strtoupper((string) preg_replace('/\s+/', '', (string) $tx->virtual_account_no));

                    return $va !== '' && str_contains($normalizedRemark, $va);
                });
            }

            // 2. Cadangan: nominal yang sama (QRIS statis / transfer manual).
            if (! $pointer) {
                $pointer = $pending->first(
                    fn (Transaction $tx): bool => abs((float) $tx->total_amount - $credit) <= $tolerance
                );
            }

            if (! $pointer) {
                continue;
            }

            if ($pointer->status === PaymentStatus::SUCCESS) {
                continue;
            }

            $matched++;

            // Anti-dobel: tandai metadata mutasi BRI.
            $transactionTime = $row['transactionTime'] ?? null;
            $this->info("MATCH: TRX #{$pointer->reference_number} (Rp ".number_format($credit, 0, ',', '.').") | remark: ".mb_substr($remark, 0, 60));

            if ($dryRun) {
                continue;
            }

            $metadata = $pointer->metadata ?? [];
            if (($metadata['bri_remark'] ?? null) === $remark
                && ($metadata['bri_transaction_time'] ?? null) === $transactionTime) {
                // Sudah pernah dicocokkan dengan baris mutasi yang sama.
                $matched--;

                continue;
            }

            $pointer->markAsSuccess();

            $pointer->update([
                // Jangan menimpa payment_gateway bri_va/qris menjadi manual:
                // nilai itu menjelaskan jalur pembayaran yang dipakai pembeli.
                'metadata' => array_merge($metadata, [
                    'bri_auto' => true,
                    'bri_transaction_time' => $transactionTime,
                    'bri_remark' => $remark,
                    'bri_settlement_account' => config('bri.account_number'),
                ]),
            ]);

            // Transaksi yang sudah LUNAS tidak perlu dicocokkan lagi.
            $pending = $pending->reject(fn (Transaction $tx): bool => $tx->is($pointer));

            $this->info("LUNAS otomatis: TRX #{$pointer->reference_number} (Rp ".number_format($credit, 0, ',', '.').")");
        }

        $this->info("Selesai: {$matched} pembayaran dicocokkan dengan mutasi BRI.".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
