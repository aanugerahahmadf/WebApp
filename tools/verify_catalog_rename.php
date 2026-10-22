<?php
/**
 * Verification script for the Product -> Catalog rename.
 * Run: php tools/verify_catalog_rename.php
 */

require __DIR__ . '/../vendor/autoload.php';

// Boot Laravel so AppServiceProvider::boot() actually registers the morph map.
// Without this the morphMap() array is empty and the checks below are meaningless.
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Catalog\Catalog;
use Illuminate\Database\Eloquent\Relations\Relation;

$fail = 0;
$check = function (string $label, $actual, $expected) use (&$fail) {
    $ok = $actual === $expected;
    if (!$ok) {
        $fail++;
    }
    printf("%-46s %-34s %s\n", $label, var_export($actual, true), $ok ? 'OK' : 'FAIL (expected ' . var_export($expected, true) . ')');
};

echo "== Catalog model ==\n";
$check('class exists', class_exists(Catalog::class), true);
// Built from parts so this check is not itself rewritten by the renamer.
$legacyClass = implode(chr(92), ['App', 'Models', 'Product', 'Product']);
$check('old App\\Models\\Product\\Product gone', class_exists($legacyClass), false);

$catalog = new Catalog();
$check('table name', $catalog->getTable(), 'products');
$check('morph alias (legacy stable)', $catalog->getMorphClass(), 'App\Models\Product');
$check('image accessor callable', is_callable([$catalog, 'getImageUrlAttribute']), true);

echo "\n== Media collections (static, no DB needed) ==\n";
$src = file_get_contents((new ReflectionClass(Catalog::class))->getFileName());
$check('declares catalog_image collection', (bool) preg_match("/addMediaCollection\('catalog_image'\)/", $src), true);
$check('keeps legacy product_image collection', (bool) preg_match("/addMediaCollection\('product_image'\)/", $src), true);
$check('image_url falls back to legacy', strpos($src, "getFirstMediaUrl('product_image')") !== false, true);

echo "\n== Morph map ==\n";
$map = Relation::morphMap();
$check('legacy key present', isset($map['App\Models\Product']), true);
$check('legacy key maps to Catalog', $map['App\Models\Product'] ?? null, Catalog::class);

echo "\n== Legacy polymorphic row resolution ==\n";
// Simulates reading a media row written BEFORE the rename.
$check('getMorphedModel(legacy) => Catalog', Relation::getMorphedModel('App\Models\Product'), Catalog::class);

echo "\n== Model references resolve ==\n";
foreach ([
    \App\Models\Category\Category::class,
    \App\Models\Cart\Cart::class,
    \App\Models\Order\Order::class,
    \App\Models\Review\Review::class,
    \App\Models\Wishlist\Wishlist::class,
    \App\Models\Package\Package::class,
] as $model) {
    $check('class loads: ' . $model, class_exists($model), true);
}

echo "\n== GraphQL / gRPC resolvers ==\n";
foreach ([
    \App\Http\GraphQL\Resolvers\CatalogResolver::class,
    \App\Http\GraphQL\Resolvers\PackageResolver::class,
    \App\Grpc\Services\CatalogService::class,
    \App\Grpc\Services\PackageService::class,
] as $svc) {
    $check('class loads: ' . $svc, class_exists($svc), true);
}

echo "\n== Canonical relation names (with product() kept as alias) ==\n";
foreach ([\App\Models\Cart\Cart::class, \App\Models\Order\Order::class, \App\Models\Review\Review::class, \App\Models\Wishlist\Wishlist::class] as $m) {
    $ref = new ReflectionClass($m);
    $check($ref->getShortName() . '::catalog()', $ref->hasMethod('catalog'), true);
    $check($ref->getShortName() . '::product() alias', $ref->hasMethod('product'), true);
}

$catRef = new ReflectionClass(Catalog::class);
foreach (['category', 'orders', 'reviews', 'wishlists'] as $rel) {
    $check('Catalog::' . $rel . '()', $catRef->hasMethod($rel), true);
}
$check('Category::catalogs()', method_exists(\App\Models\Category\Category::class, 'catalogs'), true);
$check('Category::categoryProducts() alias', method_exists(\App\Models\Category\Category::class, 'categoryProducts'), true);

echo "\n" . ($fail === 0 ? "ALL CHECKS PASSED\n" : "FAILURES: $fail\n");
exit($fail === 0 ? 0 : 1);
