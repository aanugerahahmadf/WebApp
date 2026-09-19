<?php

namespace App\Console\Commands\DailySummary;

use App\Enums\OrderStatus\OrderStatus;
use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Displays a summary of daily transactions and statuses.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $this->info("Transaction Summary for {$today->toDateString()}");
        $this->newLine();

        $this->sectionTitle('ORDERS (Hari Ini)');
        $this->table(['Status', 'Count', 'Total (Rp)'], [
            ['Pending', Order::whereDate('created_at', $today)->where('status', OrderStatus::PENDING)->count(), number_format(Order::whereDate('created_at', $today)->where('status', OrderStatus::PENDING)->sum('total_price'), 2)],
            ['Confirmed', Order::whereDate('created_at', $today)->where('status', OrderStatus::CONFIRMED)->count(), number_format(Order::whereDate('created_at', $today)->where('status', OrderStatus::CONFIRMED)->sum('total_price'), 2)],
            ['Completed', Order::whereDate('created_at', $today)->where('status', OrderStatus::COMPLETED)->count(), number_format(Order::whereDate('created_at', $today)->where('status', OrderStatus::COMPLETED)->sum('total_price'), 2)],
        ]);
        $this->newLine();

        $this->sectionTitle('TRANSACTIONS (Selesai Hari Ini)');
        $successCount = Transaction::whereDate('paid_at', $today)->where('status', 'success')->count();
        $successTotal = Transaction::whereDate('paid_at', $today)->where('status', 'success')->sum('total_amount');
        $this->info("Total Transaksi Berhasil: {$successCount} (Rp ".number_format($successTotal, 2).')');
        $this->newLine();

        $this->sectionTitle('TOPUPS');
        $this->table(['Type', 'Count', 'Total (Rp)'], [
            ['Topup (Berhasil)', Transaction::where('type', 'topup')->whereDate('paid_at', $today)->where('status', 'success')->count(), number_format(Transaction::where('type', 'topup')->whereDate('paid_at', $today)->where('status', 'success')->sum('total_amount'), 2)],
        ]);
    }

    private function sectionTitle($title)
    {
        $this->line('<fg=cyan>'.str_pad(" $title ", 60, '=', STR_PAD_BOTH).'</>');
    }
}
