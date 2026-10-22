<?php

/**
 * One-shot rewire: the User/Welcome PackageResource pages were deleted, so
 * every remaining reference has to point at the Catalog resource (or at the
 * shared CbirResultService) instead.
 */

$root = dirname(__DIR__);

/** file => [search, replace] */
$edits = [
    'app/Filament/Welcome/Widgets/ProfileOverview/ProfileOverview.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
        ['use App\\Filament\\Welcome\\Resources\\CatalogResource\\CatalogResource;', 'use App\\Filament\\Welcome\\Resources\\CatalogResource\\CatalogResource;'],
    ],
    'app/Filament/User/Widgets/ProfileOverview/ProfileOverview.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/Welcome/Resources/VoucherResource/VoucherResource.php' => [
        ["PackageResource::getUrl('index')", "CatalogResource::getUrl('index')"],
    ],
    'app/Filament/User/Resources/VoucherResource/VoucherResource.php' => [
        ["PackageResource::getUrl('index')", "CatalogResource::getUrl('index')"],
    ],
    'app/Filament/Welcome/Resources/ReviewResource/ReviewResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/User/Resources/ReviewResource/ReviewResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/Welcome/Resources/HistoryResource/HistoryResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/User/Resources/HistoryResource/HistoryResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/Welcome/Resources/OrderResource/OrderResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/User/Resources/OrderResource/OrderResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
    ],
    'app/Filament/Welcome/Resources/CartResource/CartResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
        ['PackageResource::getCheckoutWizardSteps($record->package)', 'CatalogResource::getCheckoutWizardSteps($record->package)'],
        ['PackageResource::handleCheckout($record->package, $data, $livewire)', 'CatalogResource::handleCheckout($record->package, $data, $livewire)'],
    ],
    'app/Filament/User/Resources/CartResource/CartResource.php' => [
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
        ['PackageResource::getCheckoutWizardSteps($record->package)', 'CatalogResource::getCheckoutWizardSteps($record->package)'],
        ['PackageResource::handleCheckout($record->package, $data, $livewire)', 'CatalogResource::handleCheckout($record->package, $data, $livewire)'],
    ],
    'app/Services/ChatService/ChatService.php' => [
        ["self::resourceUrl(PackageResource::class)", "self::resourceUrl(CatalogResource::class)"],
    ],
    'app/Filament/Welcome/Pages/MessagesPage/MessagesPage.php' => [
        ["\$resource = \$origin['type'] === 'catalog' ? CatalogResource::class : PackageResource::class;",
         "// Packages are tiers now, so every browsable origin resolves to the catalog.\n            \$resource = CatalogResource::class;"],
    ],
    'app/Filament/User/Pages/MessagesPage/MessagesPage.php' => [
        ["\$resource = \$origin['type'] === 'catalog' ? CatalogResource::class : PackageResource::class;",
         "// Packages are tiers now, so every browsable origin resolves to the catalog.\n            \$resource = CatalogResource::class;"],
    ],
];

// The three Messages components share the same substitutions.
foreach (['Admin', 'User', 'Welcome'] as $panel) {
    $edits["app/Livewire/{$panel}/Messages/Messages/Messages.php"] = [
        ['PackageResource::buildCbirMixedResults(', 'CbirResultService::buildCbirMixedResults('],
        ["PackageResource::getModelLabel()", "__('Paket')"],
        ["PackageResource::getUrl()", "CatalogResource::getUrl()"],
        ["\$this->tryResourceUrl(PackageResource::class)", "\$this->tryResourceUrl(CatalogResource::class)"],
    ];
}

// Blade views: the label is the only thing they need from the resource.
foreach (['User', 'Welcome'] as $panel) {
    $edits["resources/views/{$panel}/emails/order-payment-notification/order-payment-notification.blade.php"] = [
        ["\\App\\Filament\\{$panel}\\Resources\\PackageResource\\PackageResource::getModelLabel()", "__('Paket')"],
    ];
    $edits["resources/views/{$panel}/pdf/order-invoice/order-invoice.blade.php"] = [
        ["\\App\\Filament\\{$panel}\\Resources\\PackageResource\\PackageResource::getModelLabel()", "__('Paket')"],
    ];
    $edits["resources/views/{$panel}/components/combined-catalog-grid/combined-catalog-grid.blade.php"] = [
        ["\\App\\Filament\\{$panel}\\Resources\\PackageResource\\PackageResource::getUrl('view', ['record' => \$item])",
         "\\App\\Filament\\{$panel}\\Resources\\CatalogResource\\CatalogResource::getUrl('view', ['record' => \$item])"],
    ];
    $edits["resources/views/{$panel}/components/cbir-results-preview/cbir-results-preview.blade.php"] = [
        ["\\App\\Filament\\{$panel}\\Resources\\PackageResource\\PackageResource::getUrl('view', ['record' => \$data['id'] ?? 0])",
         "\\App\\Filament\\{$panel}\\Resources\\CatalogResource\\CatalogResource::getUrl('view', ['record' => \$data['id'] ?? 0])"],
    ];
}

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
        // No-op identity pairs keep the table readable without extra ifs.
        if ($search === $replace) {
            continue;
        }

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
