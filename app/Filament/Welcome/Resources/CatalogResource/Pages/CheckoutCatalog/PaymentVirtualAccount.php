<?php

namespace App\Filament\Welcome\Resources\CatalogResource\Pages\CheckoutCatalog;

use Filament\Pages\Page;

class PaymentVirtualAccount extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'Welcome.payment-virtual-account.payment-virtual-account';

    protected static bool $shouldRegisterNavigation = false;
}

