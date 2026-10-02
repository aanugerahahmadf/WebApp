<?php

namespace App\Filament\User\Widgets\StatsOverview;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $navigationSort = 1;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getExtraAttributes(): array
    {
        return [
            'class' => implode(' ', [
                'user-home-stats',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid-cols-2',
                '[&_.fi-wi-stats-overview-stats-ctn]:!gap-3',
                '[&_.fi-wi-stats-overview-stat]:!p-4',
                '[&_.fi-wi-stats-overview-stat-label]:!text-sm',
                '[&_.fi-wi-stats-overview-stat-value]:!text-2xl',
                '[&_.fi-wi-stats-overview-stat-description]:!text-xs',
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
        $user = Auth::user();
        $name = $user->full_name ?? $user->username ?? __('User');

        return [
            Stat::make(__('filament-panels::widgets/account-widget.welcome'), $name)
                ->description(__('Make your special moment today'))
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-welcome h-full col-span-2',
                    'style' => 'grid-column: 1 / -1;',
                ]),
        ];
    }
}
