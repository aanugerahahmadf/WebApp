<?php

namespace App\Filament\User\Widgets\ProfileOverview;

use App\Filament\User\Pages\EditProfilePage\EditProfilePage;
use App\Filament\User\Resources\HistoryResource\HistoryResource;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfileOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getExtraAttributes(): array
    {
        return [
            'class' => implode(' ', [
                'profile-overview-stats',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid-cols-2',
                '[&_.fi-wi-stats-overview-stats-ctn]:!gap-3',
                'md:[&_.fi-wi-stats-overview-stats-ctn]:!gap-4',
            ]),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        return [
            // Row 1: Edit Profile — full width (span 2)
            Stat::make(__('Edit Profile'), '')
                ->icon('heroicon-o-user-circle')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card profile-stat-card cursor-pointer hover:scale-105 transition-transform h-full',
                    'style' => 'grid-column: 1 / -1;',
                    'onclick' => "window.location.href='".EditProfilePage::getUrl()."'",
                ]),

            // Row 2: 2 kolom — Katalog Paket | Katalog Bunga

            Stat::make(__('Katalog Paket Bunga'), '')
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card profile-stat-card cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".PackageResource::getUrl()."'",
                ]),

            Stat::make(__('Katalog Bunga'), '')
                ->icon('heroicon-o-shopping-bag')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card profile-stat-card cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".ProductResource::getUrl()."'",
                ]),

            // Row 3: Riwayat — full width (span 2)
            Stat::make(__('Riwayat'), '')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card profile-stat-card cursor-pointer hover:scale-105 transition-transform h-full',
                    'style' => 'grid-column: 1 / -1;',
                    'onclick' => "window.location.href='".HistoryResource::getUrl()."'",
                ]),

            // Row 4: Ulasan — full width (span 2)
            Stat::make(__('Ulasan'), '')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card profile-stat-card cursor-pointer hover:scale-105 transition-transform h-full',
                    'style' => 'grid-column: 1 / -1;',
                    'onclick' => "window.location.href='".ReviewResource::getUrl()."'",
                ]),
        ];
    }
}
