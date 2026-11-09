<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$models = [
    'packages' => App\Models\Package\Package::class,
    'catalogs' => App\Models\Catalog\Catalog::class,
    'orders' => App\Models\Order\Order::class,
];

foreach ($models as $table => $class) {
    $cols = array_map(fn ($c) => $c->column_name, DB::select(
        "select column_name from information_schema.columns where table_name = ? order by ordinal_position",
        [$table]
    ));

    $model = new $class();
    $appends = $model->getAppends();
    $reflection = new ReflectionClass($class);
    $methods = array_map(fn (ReflectionMethod $m) => $m->name, $reflection->getMethods(ReflectionMethod::IS_PUBLIC));

    echo '=== '.$table.' ('.$class.') ==='.PHP_EOL;
    echo 'cols: '.implode(', ', $cols).PHP_EOL;

    $missing = [];
    foreach ($cols as $col) {
        if (! str_contains($col, '_')) {
            continue;
        }
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $col))));
        if (in_array($camel, ['createdAt', 'updatedAt'], true)) {
            continue;
        }
        $hasMutator = in_array('get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $camel))).'Attribute', $methods, true);
        $hasColumnAccess = in_array($camel, $appends, true);
        if (! $hasMutator && ! $hasColumnAccess) {
            $missing[] = $col.' -> '.$camel;
        }
    }
    echo 'missing camelCase accessor: '.($missing === [] ? '(none)' : implode(' | ', $missing)).PHP_EOL.PHP_EOL;
}
