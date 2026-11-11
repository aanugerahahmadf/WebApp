<?php

namespace App\Filament\User\Widgets\StatsOverview;

use App\Filament\User\Pages\EditProfilePage\EditProfilePage;
use App\Support\AppIcon\AppIcon;
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
        $user = Auth::user();
        $name = $user?->full_name ?? $user?->username ?? __('User');
        $avatarUrl = $user?->getFilamentAvatarUrl() ?? $user?->avatar_url ?? null;
        $profileUrl = EditProfilePage::getUrl(panel: 'user');

        return [
            Stat::make(__('filament-panels::widgets/account-widget.welcome'), $name)
                ->description(__('Make your special moment today'))
                ->descriptionIcon(AppIcon::SPARKLES)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-welcome stats-profile-card h-full col-span-2',
                    'style' => 'grid-column: 1 / -1;',
                    'data-avatar-url' => $avatarUrl,
                    'data-avatar-href' => $profileUrl,
                    'data-avatar-alt' => $name,
                ]),
        ];
    }
}
