<?php

namespace App\Filament\Welcome\Resources\CatalogResource\Pages\CheckoutCatalog;

use Filament\Pages\Page;

class PaymentQris extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'Welcome.payment-qris.payment-qris';

    protected static bool $shouldRegisterNavigation = false;
}

