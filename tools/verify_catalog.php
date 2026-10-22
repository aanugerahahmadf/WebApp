<?php

require dirname(__DIR__).'/vendor/autoload.php';

$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Services\CbirResultService\CbirResultService;

echo "=== COUNTS ===\n";
printf("Catalog            : %d\n", Catalog::count());
printf("Tier (packages)    : %d\n", Package::count());
printf("Catalog no tier    : %d\n", Catalog::doesntHave('tiers')->count());
printf("Catalog no photo   : %d\n", Catalog::whereDoesntHave('media')->count());
printf("Tier descriptions  : %d distinct / %d rows\n", Package::distinct()->count('description'), Package::count());
printf("Tier names         : %s\n", Package::distinct()->pluck('name')->implode(' | '));

echo "\n=== ONE CATALOG IN DETAIL ===\n";
$catalog = Catalog::with(['tiers', 'media'])->find(30);
printf("%s  (harga dasar Rp %s)\n", $catalog->name, number_format($catalog->final_price, 0, ',', '.'));
printf("foto: %d | thumbnail: %s\n", $catalog->media->count(), basename(parse_url($catalog->thumbnail_url, PHP_URL_PATH) ?: '-'));

foreach ($catalog->tiers as $tier) {
    printf(
        "  %d. %-48s Rp %-14s | %s...\n",
        $tier->sort_order,
        $tier->name,
        number_format($tier->final_price, 0, ',', '.'),
        mb_substr(strip_tags((string) $tier->description), 0, 62)
    );
}

$prices = $catalog->tiers->pluck('price')->all();
printf("harga semua tier berbeda: %s\n", count(array_unique($prices)) === count($prices) ? 'YA' : 'TIDAK');

echo "\n=== PRICE RANGE ACROSS ALL CATALOGS ===\n";
$byTier = Package::query()
    ->selectRaw('sort_order, COUNT(*) c, MIN(price) mn, MAX(price) mx, COUNT(DISTINCT price) d')
    ->groupBy('sort_order')
    ->orderBy('sort_order')
    ->get();
foreach ($byTier as $row) {
    $name = Package::where('sort_order', $row->sort_order)->value('name');
    printf("  %d. %-46s n=%-4d Rp %s .. Rp %s\n", $row->sort_order, $name, $row->c, number_format($row->mn, 0, ',', '.'), number_format($row->mx, 0, ',', '.'));
}

echo "\n=== GALLERY (gallery_urls) ===\n";
$gallery = $catalog->gallery_urls;
printf("jumlah foto: %d\n", count($gallery));
foreach (array_slice($gallery, 0, 2) as $i => $g) {
    printf("  [%d] url=%s\n      thumb=%s\n", $i + 1, basename((string) parse_url($g['url'], PHP_URL_PATH)), basename((string) parse_url($g['thumb'], PHP_URL_PATH)));
}

echo "\n=== CBIR HYDRATOR ===\n";
$mixed = CbirResultService::buildCbirMixedResults([
    ['type' => 'product', 'owner_id' => 1, 'score' => 0.82],
    ['type' => 'package', 'owner_id' => 3, 'score' => 0.99],
    ['type' => 'catalog', 'owner_id' => 2, 'score' => 0.75],
]);
foreach (array_slice($mixed, 0, 3) as $r) {
    printf("  type=%-8s sim=%-6s %s\n", $r['type'], $r['similarity'], $r['data']['name']);
}
