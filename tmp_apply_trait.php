<?php

/**
 * Sekali-pakai: menambahkan trait ResolvesCamelCaseAttributes ke semua model
 * yang dipakai GraphQL (type @model + model yang dikembalikan resolver).
 * Idempoten -- file yang sudah mengandung nama trait dilewati.
 */

$models = [
    // @model(class: ...) di graphql/schema.graphql
    'Cart/Cart.php',
    'Catalog/Catalog.php',
    'Category/Category.php',
    'Favorite/Favorite.php',
    'Order/Order.php',
    'Package/Package.php',
    'Review/Review.php',
    'User/User.php',
    'Voucher/Voucher.php',
    // Dikembalikan oleh App\Http\GraphQL\Resolvers\*
    'Cbir/CbirFeedback.php',
    'Cbir/CbirSearch.php',
    'Cbir/CbirSearchResult.php',
    'Help/Help.php',
    'Home/HeroBanner.php',
    'Home/Promotion.php',
    'Inbox/Inbox.php',
    'LegalPage/LegalPage.php',
    'Message/Message.php',
    'PrivacyPolicy/PrivacyPolicy.php',
    'ReviewReply/ReviewReply.php',
    'ReviewVote/ReviewVote.php',
    'Search/SearchLog.php',
    'TermsOfService/TermsOfService.php',
    'UserFcmDevice/UserFcmDevice.php',
    'WeddingDecorationPolicy/WeddingDecorationPolicy.php',
];

$import = 'use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;';
$traitUse = '    use ResolvesCamelCaseAttributes;';

$root = __DIR__;
$changed = [];
$skipped = [];
$failed = [];

foreach ($models as $rel) {
    $path = $root.'/app/Models/'.$rel;

    if (! is_file($path)) {
        $failed[] = "$rel (file tidak ada)";
        continue;
    }

    $src = file_get_contents($path);

    if (str_contains($src, 'ResolvesCamelCaseAttributes')) {
        $skipped[] = $rel;
        continue;
    }

    // Pertahankan ending baris asli supaya diff tidak berisik.
    // Pemisah dibatasi \r\n | \n | \r: \R ikut mencocokkan byte 0x85 yang
    // bisa muncul di dalam sekuens UTF-8 multi-byte dan memecah baris komentar.
    $eol = str_contains($src, "\r\n") ? "\r\n" : "\n";
    $lines = preg_split('/\r\n|\n|\r/', rtrim($src, "\r\n"));

    // Indeks deklarasi class.
    $classIdx = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/', $line)) {
            $classIdx = $i;
            break;
        }
    }

    if ($classIdx === null) {
        $failed[] = "$rel (deklarasi class tidak ditemukan)";
        continue;
    }

    // Indeks blok `use ...;` level atas terakhir sebelum class.
    $lastUse = null;
    for ($i = 0; $i < $classIdx; $i++) {
        if (preg_match('/^use\s+[A-Za-z_\\\\]/', $lines[$i])) {
            $lastUse = $i;
        }
    }

    $nsIdx = null;
    for ($i = 0; $i < $classIdx; $i++) {
        if (preg_match('/^namespace\s+/', $lines[$i])) {
            $nsIdx = $i;
            break;
        }
    }

    if ($lastUse !== null) {
        array_splice($lines, $lastUse + 1, 0, [$import, '']);
    } elseif ($nsIdx !== null) {
        array_splice($lines, $nsIdx + 1, 0, ['', $import]);
    } else {
        $failed[] = "$rel (namespace tidak ditemukan)";
        continue;
    }

    // Cari kurung buka badan class (bisa di baris yang sama atau baris berikut).
    $classIdx = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/', $line)) {
            $classIdx = $i;
            break;
        }
    }

    $braceIdx = null;
    for ($i = $classIdx; $i < count($lines); $i++) {
        if (str_contains($lines[$i], '{')) {
            $braceIdx = $i;
            break;
        }
    }

    if ($braceIdx === null) {
        $failed[] = "$rel (kurung buka class tidak ditemukan)";
        continue;
    }

    array_splice($lines, $braceIdx + 1, 0, [$traitUse]);

    $out = implode($eol, $lines).$eol;

    if (file_put_contents($path, $out) === false) {
        $failed[] = "$rel (gagal menulis)";
        continue;
    }

    $changed[] = $rel;
}

echo 'CHANGED ('.count($changed)."):\n  ".implode("\n  ", $changed)."\n";
echo 'SKIPPED ('.count($skipped)."):\n  ".implode("\n  ", $skipped)."\n";
echo 'FAILED ('.count($failed)."):\n  ".implode("\n  ", $failed)."\n";
