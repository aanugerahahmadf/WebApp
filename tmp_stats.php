<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Hanya untuk skrip diagnostik lokal: menampilkan pesan exception asli tanpa
// menyentuh .env (APP_DEBUG di .env tetap false).
config(['app.debug' => true]);

$queries = [
    'catalogStats' => '{ catalogStats { totalCatalogs } }',
    'packageStats' => '{ packageStats { totalPackages } }',
    'reviewStats' => '{ reviewStats { totalReviews } }',
    'voucherStats' => '{ voucherStats { totalVouchers } }',
    'orderStats' => '{ orderStats { totalOrders } }',
    'cbirStats' => '{ cbirStats { totalSearches } }',
];

foreach ($queries as $name => $query) {
    echo "=== {$name} ===".PHP_EOL;

    try {
        $result = app(\App\Services\GraphQLExecutor\GraphQLExecutor::class)
            ->execute($query, [], null, []);

        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL;
    } catch (\Throwable $e) {
        echo 'EXCEPTION '.get_class($e).': '.$e->getMessage().PHP_EOL;
    }

    echo PHP_EOL;
}
