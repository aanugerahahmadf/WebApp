<?php

namespace App\Console\Commands\UpdateOrderStatus;

use App\Enums\OrderStatus\OrderStatus;
use App\Models\Order\Order;
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

        // 2. Auto Cancel: Jika pembayaran belum lunas setelah 2 hari, set ke CANCELLED
        $cancelledCount = Order::where('status', OrderStatus::PENDING)
            ->where('payment_status', 'unpaid')
            ->where('created_at', '<', Carbon::now()->subDays(2))
            ->update(['status' => OrderStatus::CANCELLED]);

        if ($cancelledCount > 0) {
            $this->info("Successfully cancelled {$cancelledCount} unpaid orders.");
        }

        $this->info('Order status update completed.');
    }
}
