<?php

namespace App\Enums\PaymentStatus;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => __('Tertunda'),
            self::PROCESSING => __('Diproses'),
            self::SUCCESS => __('Berhasil'),
            self::FAILED => __('Gagal'),
            self::EXPIRED => __('Kadaluarsa'),
            self::CANCELLED => __('Dibatalkan'),
            self::REFUNDED => __('Dikembalikan'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'gray',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-m-clock',
            self::PROCESSING => 'heroicon-m-arrow-path',
            self::SUCCESS => 'heroicon-m-check-circle',
            self::FAILED => 'heroicon-m-x-circle',
            self::EXPIRED => 'heroicon-m-clock',
            self::CANCELLED => 'heroicon-m-x-circle',
            self::REFUNDED => 'heroicon-m-backspace',
        };
    }
}
