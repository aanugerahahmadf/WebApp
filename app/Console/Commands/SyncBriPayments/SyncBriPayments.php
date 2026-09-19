<?php

namespace App\Console\Commands\SyncBriPayments;

use App\Models\Transaction\Transaction;
use App\Services\BriService\BriService;
use Illuminate\Console\Command;

class SyncBriPayments extends Command
{
    protected $signature = 'app:sync-bri-payments
        {--dry-run : Tampilkan hasil tanpa menandai LUNAS}';

    protected $description = 'Cek mutasi BRI dan otomatis tandai pembayaran manual (QRIS/transfer) sebagai LUNAS';

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

        // Hanya transaksi order manual yang masih menunggu verifikasi.
        $pending = Transaction::where('type', 'order')
            ->where('payment_gateway', 'manual')
            ->whereIn('status', ['pending', 'processing'])
            ->with('order')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Tidak ada pembayaran manual yang menunggu.');

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

            $pointer = null;
            foreach ($pending as $tx) {
                if ($tx->status === 'success') {
                    continue;
                }
                if (abs((float) $tx->total_amount - $credit) <= $tolerance) {
                    $pointer = $tx;
                    break;
                }
            }

            if (! $pointer) {
                continue;
            }

            $matched++;

            // Anti-dobel: tandai metadata mutasi BRI.
            $remark = (string) ($row['remark'] ?? '');
            $this->info("MATCH: TRX #{$pointer->reference_number} (Rp ".number_format($credit, 0, ',', '.').") | remark: ".mb_substr($remark, 0, 60));

            if ($dryRun) {
                continue;
            }

            if (isset($pointer->metadata['bri_remark'])
                && $pointer->metadata['bri_remark'] === $remark
                && $pointer->metadata['bri_transaction_time'] === ($row['transactionTime'] ?? null)) {
                // Sudah pernah dicocokkan dengan baris mutasi yang sama.
                $matched--;

                continue;
            }

            $pointer->markAsSuccess();

            $pointer->update([
                'payment_gateway' => 'manual',
                'metadata' => array_merge($pointer->metadata ?? [], [
                    'bri_auto' => true,
                    'bri_transaction_time' => $row['transactionTime'] ?? null,
                    'bri_remark' => $remark,
                ]),
            ]);

            $this->info("LUNAS otomatis: TRX #{$pointer->reference_number} (Rp ".number_format($credit, 0, ',', '.').")");
        }

        $this->info("Selesai: {$matched} pembayaran dicocokkan dengan mutasi BRI.".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}