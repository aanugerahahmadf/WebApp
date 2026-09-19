<?php

namespace App\Enums\DiscountType;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasColor, HasIcon, HasLabel
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED => __('Jumlah Tetap (Rp)'),
            self::PERCENTAGE => __('Persentase (%)'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FIXED => 'info',
            self::PERCENTAGE => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FIXED => 'heroicon-m-currency-dollar',
            self::PERCENTAGE => 'heroicon-m-percent-badge',
        };
    }
}
