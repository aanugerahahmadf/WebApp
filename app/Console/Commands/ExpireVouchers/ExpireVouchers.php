<?php

namespace App\Console\Commands\ExpireVouchers;

use App\Models\Voucher\Voucher;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireVouchers extends Command
{
    protected $signature = 'app:expire-vouchers';

    protected $description = 'Disables vouchers that have passed their expiration date.';

    public function handle()
    {
        $this->info('Checking for expired vouchers...');

        $expiredVouchers = Voucher::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->get();

        if ($expiredVouchers->isEmpty()) {
            $this->info('No expired vouchers found.');

            return;
        }

        foreach ($expiredVouchers as $voucher) {
            $voucher->update(['is_active' => false]);
            $this->line("Voucher {$voucher->code} has been deactivated.");
        }

        $this->info("Successfully deactivated {$expiredVouchers->count()} vouchers.");
    }
}
