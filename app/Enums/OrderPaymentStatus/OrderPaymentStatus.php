<?php

namespace App\Enums\OrderPaymentStatus;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderPaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case UNPAID = 'unpaid';
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNPAID => __('Belum Bayar'),
            self::PENDING => __('Menunggu Konfirmasi'),
            self::PARTIAL => __('DP / Sebagian'),
            self::PAID => __('Lunas'),
            self::FAILED => __('Gagal'),
            self::REFUNDED => __('Refund'),
            self::CANCELLED => __('Dibatalkan'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PENDING => 'warning',
            self::PARTIAL => 'info',
            self::PAID => 'success',
            self::FAILED => 'danger',
            self::REFUNDED => 'gray',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::UNPAID => '/images/icons/x_circle.svg',
            self::PENDING => '/images/icons/clock.svg',
            self::PARTIAL => '/images/icons/banknotes.svg',
            self::PAID => '/images/icons/check_circle.svg',
            self::FAILED => '/images/icons/alert.svg',
            self::REFUNDED => '/images/icons/refresh.svg',
            self::CANCELLED => '/images/icons/x_circle.svg',
        };
    }
}
