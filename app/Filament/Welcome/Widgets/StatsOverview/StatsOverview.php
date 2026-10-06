<?php

namespace App\Filament\Welcome\Widgets\StatsOverview;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $navigationSort = 1;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'Shared.widgets.stats-overview';

    protected function getColumns(): int
    {
        return count($this->getStats());
    }

    protected function getStats(): array
    {
        // Panel welcome bisa dijelajahi guest: sapaan memakai nama user
        // atau "Tamu" bila belum login.
        $user = Auth::user();
        $name = $user?->full_name ?? $user?->username ?? __('Tamu');

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
