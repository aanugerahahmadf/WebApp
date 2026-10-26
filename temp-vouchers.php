<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'vouchers='.App\Models\Voucher\Voucher::count().PHP_EOL;

foreach (App\Models\Voucher\Voucher::with('discount')->get() as $voucher) {
    echo $voucher->code.' type='.var_export($voucher->discount_type, true).' amount='.$voucher->discount_amount.PHP_EOL;
}
