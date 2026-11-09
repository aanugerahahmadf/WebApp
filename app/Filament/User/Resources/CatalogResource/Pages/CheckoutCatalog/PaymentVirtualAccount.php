<?php

namespace App\Filament\User\Resources\CatalogResource\Pages\CheckoutCatalog;

use Filament\Pages\Page;

class PaymentVirtualAccount extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'User.payment-virtual-account.payment-virtual-account';

    protected static bool $shouldRegisterNavigation = false;
}

