<?php

namespace App\Enums\TransactionType;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasColor, HasIcon, HasLabel
{
    case TOPUP = 'topup';
    case ORDER = 'order';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TOPUP => __('Top Up'),
            self::ORDER => __('Pesanan'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TOPUP => 'success',
            self::ORDER => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TOPUP => '/images/icons/arrow_circle.svg',
            self::ORDER => '/images/icons/bag.svg',
        };
    }
}
