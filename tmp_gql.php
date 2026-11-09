<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$queries = [
    'catalogStats' => '{ catalogStats { totalCatalogs } }',
    'packageStats' => '{ packageStats { totalPackages } }',
    'heroBanners' => '{ heroBanners { id title } }',
    'homeData' => '{ homeData { featuredCatalogs { id } } }',
    'searchFilters' => '{ searchFilters { __typename } }',
];

foreach ($queries as $name => $query) {
    echo "=== {$name} ===".PHP_EOL;

    try {
        $result = app(\App\Services\GraphQLExecutor\GraphQLExecutor::class)
            ->execute($query, [], null, []);

        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL;
    } catch (\Throwable $e) {
        echo 'EXCEPTION: '.get_class($e).': '.$e->getMessage().PHP_EOL;
        $trace = array_filter($e->getTrace(), function ($f) {
            $file = $f['file'] ?? '';
            $line = $f['line'] ?? 0;

            return str_contains($file, 'App') || str_contains($file, 'app/');
        });
        foreach (array_slice($trace, 0, 8) as $f) {
            echo '    '.($f['file'] ?? '?').':'.($f['line'] ?? '?').PHP_EOL;
        }
    }

    echo PHP_EOL;
}
