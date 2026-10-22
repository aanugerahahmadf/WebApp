<?php

namespace App\Enums\OrderStatus;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case EVENT_DAY = 'event_day';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => __('Menunggu'),
            self::CONFIRMED => __('Dikonfirmasi'),
            self::PREPARING => __('Sedang Disiapkan'),
            self::EVENT_DAY => __('Hari-H'),
            self::COMPLETED => __('Selesai'),
            self::CANCELLED => __('Dibatalkan'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'primary',
            self::PREPARING => 'info',
            self::EVENT_DAY => 'success',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => '/images/icons/clock.svg',
            self::CONFIRMED => '/images/icons/check_circle.svg',
            self::PREPARING => '/images/icons/refresh.svg',
            self::EVENT_DAY => '/images/icons/calendar_days.svg',
            self::COMPLETED => '/images/icons/check_badge.svg',
            self::CANCELLED => '/images/icons/x_circle.svg',
        };
    }
}
