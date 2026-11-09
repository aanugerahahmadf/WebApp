<?php

namespace App\Filament\User\Resources\CatalogResource\Pages\CheckoutCatalog;

use Filament\Pages\Page;

class PaymentQris extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'User.payment-qris.payment-qris';

    protected static bool $shouldRegisterNavigation = false;
}

