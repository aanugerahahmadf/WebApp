<?php

/**
 * One-shot rewire for Product -> Catalog in API controllers.
 * Run: php tools/rewire_api_controllers.php
 */

$root = dirname(__DIR__);

/** @var array<string, array<int, array{0:string,1:string}>> */
$edits = [
    'app/Http/Controllers/Api/User/OrderController/OrderController.php' => [
        // Validation rules
        ["'product_id' => 'nullable|integer'", "'catalog_id' => 'nullable|integer'"],
        ["'product_id' => 'nullable|exists:products,id'", "'catalog_id' => 'nullable|exists:catalogs,id'"],
        // Variable names
        ["\$product = ", "\$catalog = "],
        ["\$products = ", "\$catalogs = "],
        ["\$productId", "\$catalogId"],
        // DB table references
        ["DB::table('products')", "DB::table('catalogs')"],
        ["->where('id', \$catalog->id)", "->where('id', \$catalog->id)"], // no change
        // Model references
        ["Product::", "Catalog::"],
        // Order creation
        ["'product_id' => \$catalog?->id", "'catalog_id' => \$catalog?->id"],
        // Response fields (keep product_id for backward compat in JSON, but note it)
        ["'resource_type' => \$order->package_id ? 'package' : 'catalog'", "'resource_type' => \$order->package_id ? 'package' : 'catalog'"],
    ],
    'app/Http/Controllers/Api/User/CartController/CartController.php' => [
        ["'product_id' => 'nullable|exists:products,id'", "'catalog_id' => 'nullable|exists:catalogs,id'"],
        ["\$productId", "\$catalogId"],
        ["\$product = ", "\$catalog = "],
        ["Product::", "Catalog::"],
        ["DB::table('products')", "DB::table('catalogs')"],
    ],
    'app/Http/Controllers/Api/User/ReviewController/ReviewController.php' => [
        ["'product_id' => 'nullable|exists:products,id'", "'catalog_id' => 'nullable|exists:catalogs,id'"],
        ["\$productId", "\$catalogId"],
        ["\$product = ", "\$catalog = "],
        ["Product::", "Catalog::"],
        ["DB::table('products')", "DB::table('catalogs')"],
    ],
    'app/Http/Controllers/Api/User/SearchController/SearchController.php' => [
        ["Product::", "Catalog::"],
        ["\$products = ", "\$catalogs = "],
        ["forProducts()", "forCatalogs()"],
    ],
    'app/Http/Controllers/Api/Admin/CatalogController/CatalogController.php' => [
        ["Product::", "Catalog::"],
        ["\$products = ", "\$catalogs = "],
        ["'product_image'", "'catalog_image'"],
    ],
    'app/Http/Controllers/Api/Admin/PackageController/PackageController.php' => [
        ["Package::", "Package::"],
    ],
];

$touched = 0;

foreach ($edits as $relative => $pairs) {
    $path = $root.'/'.$relative;

    if (! is_file($path)) {
        fwrite(STDERR, "MISSING  {$relative}\n");

        continue;
    }

    $original = file_get_contents($path);
    $content = $original;

    foreach ($pairs as [$search, $replace]) {
        $content = str_replace($search, $replace, $content);
    }

    if ($content === $original) {
        continue;
    }

    file_put_contents($path, $content);
    $touched++;
    echo "updated  {$relative}\n";
}

echo "\n{$touched} file(s) updated\n";