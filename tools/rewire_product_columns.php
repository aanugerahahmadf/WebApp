<?php

/**
 * One-shot rewire for the Product -> Catalog database rename.
 *
 * The `products` table is now `catalogs` and every `product_id` FK is now
 * `catalog_id`, so the PHP side has to follow. The `product()` relation aliases
 * stay (they are read by historical API payloads and views) but they resolve
 * against the new column.
 */

$root = dirname(__DIR__);

/** @var array<string, array<int, array{0:string,1:string}>> */
$edits = [
    'app/Models/Order/Order.php' => [
        ["belongsTo(Catalog::class, 'product_id')", "belongsTo(Catalog::class, 'catalog_id')"],
        ["'product_id',", "'catalog_id',"],
        ["The FK column is still named `product_id` (renaming it would invalidate\n     * every historical row, so only the PHP-side relation name was migrated).",
         "Both the relation and the column are now `catalog_id`."],
    ],
    'app/Models/Cart/Cart.php' => [
        ["belongsTo(Catalog::class, 'product_id')", "belongsTo(Catalog::class, 'catalog_id')"],
        ["'product_id',", "'catalog_id',"],
    ],
    'app/Models/Review/Review.php' => [
        ["belongsTo(Catalog::class, 'product_id')", "belongsTo(Catalog::class, 'catalog_id')"],
        ["'product_id',", "'catalog_id',"],
    ],
    'app/Models/Wishlist/Wishlist.php' => [
        ["belongsTo(Catalog::class, 'product_id')", "belongsTo(Catalog::class, 'catalog_id')"],
        ["'product_id',", "'catalog_id',"],
    ],
    'app/Models/Category/Category.php' => [
        // `categoryProducts()` delegated to `catalogs()`, which made it infinitely
        // recursive once the alias was renamed.
        ["return \$this->catalogs();", "return \$this->hasMany(Catalog::class, 'category_id');"],
        ['Canonical relation name (the model was renamed Catalog -> Catalog).', 'Catalog rows in this category.'],
        ['Alias kept for backward compatibility with the pre-rename name.', 'Alias kept for backward compatibility with the pre-rename name.'],
    ],
    'app/Providers/AppServiceProvider/AppServiceProvider.php' => [
        ["'App\\Models\\Product' => Catalog::class,", "'App\\Models\\Catalog' => Catalog::class,"],
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
