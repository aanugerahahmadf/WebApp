<?php

// Verifikasi: setiap model target punya tepat satu import + satu pemakaian
// trait, dan tidak ada baris yang terpecah (sisa bug \R sebelumnya).

$models = [
    'Cart/Cart.php', 'Catalog/Catalog.php', 'Category/Category.php',
    'Favorite/Favorite.php', 'Order/Order.php', 'Package/Package.php',
    'Review/Review.php', 'User/User.php', 'Voucher/Voucher.php',
    'Cbir/CbirFeedback.php', 'Cbir/CbirSearch.php', 'Cbir/CbirSearchResult.php',
    'Help/Help.php', 'Home/HeroBanner.php', 'Home/Promotion.php',
    'Inbox/Inbox.php', 'LegalPage/LegalPage.php', 'Message/Message.php',
    'PrivacyPolicy/PrivacyPolicy.php', 'ReviewReply/ReviewReply.php',
    'ReviewVote/ReviewVote.php', 'Search/SearchLog.php',
    'TermsOfService/TermsOfService.php', 'UserFcmDevice/UserFcmDevice.php',
    'WeddingDecorationPolicy/WeddingDecorationPolicy.php',
];

$import = 'use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;';
$bad = 0;

foreach ($models as $rel) {
    $path = __DIR__.'/app/Models/'.$rel;
    $src = file_get_contents($path);
    $lines = preg_split('/\r\n|\n|\r/', $src);

    $nImport = 0;
    $nTrait = 0;
    $suspect = [];

    foreach ($lines as $i => $line) {
        if (trim($line) === $import) {
            $nImport++;
        }
        if (trim($line) === 'use ResolvesCamelCaseAttributes;') {
            $nTrait++;
        }
        // Baris hasil pemecahan byte: diawali karakter mojibake yang tidak
        // mungkin menjadi awal baris PHP yang sah.
        if (preg_match('/^\x{c2}\x{85}|^¡/', $line)) {
            $suspect[] = ($i + 1).': '.mb_substr($line, 0, 40);
        }
    }

    $ok = $nImport === 1 && $nTrait === 1 && $suspect === [];
    if (! $ok) {
        $bad++;
    }

    printf(
        "%-45s import=%d trait=%d suspect=%d %s\n",
        $rel,
        $nImport,
        $nTrait,
        count($suspect),
        $ok ? 'OK' : 'FAIL'
    );

    foreach ($suspect as $s) {
        echo "      $s\n";
    }
}

echo $bad === 0 ? "\nSEMUA BERSIH\n" : "\n$bad FILE BERMASALAH\n";
