<?php

namespace App\Console\Commands\UpdateOrderStatus;

use App\Enums\OrderStatus\OrderStatus;
use App\Models\Order\Order;
use App\Support\PaymentWindow\PaymentWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateOrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-order-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update order status based on date and payment status.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating order statuses...');

        // 1. Auto Complete: Jika hari H sudah lewat, set ke COMPLETED
        $completedCount = Order::where('status', OrderStatus::EVENT_DAY)
            ->where('event_date', '<', Carbon::today())
            ->update(['status' => OrderStatus::COMPLETED]);

        if ($completedCount > 0) {
            $this->info("Successfully moved {$completedCount} orders to COMPLETED.");
        }

        // 2. Auto Cancel setelah satu payment window (24 jam secara default).
        // save() sengaja dipakai agar observer mengembalikan stok dan mencatat riwayat.
        $cancelledCount = 0;
        Order::where('status', OrderStatus::PENDING)
            ->whereIn('payment_status', ['unpaid', 'pending', 'failed'])
            ->where('created_at', '<', Carbon::now()->subHours(PaymentWindow::expiryHours()))
            ->chunkById(100, function ($orders) use (&$cancelledCount): void {
                foreach ($orders as $order) {
                    $order->status = OrderStatus::CANCELLED;
                    $order->cancelled_at = now();
                    $order->save();
                    $cancelledCount++;
                }
            });

        if ($cancelledCount > 0) {
            $this->info("Successfully cancelled {$cancelledCount} unpaid orders.");
        }

        $this->info('Order status update completed.');
    }
}
