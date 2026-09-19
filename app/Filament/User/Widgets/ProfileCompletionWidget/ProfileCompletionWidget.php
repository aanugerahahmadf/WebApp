<?php

namespace App\Filament\User\Widgets\ProfileCompletionWidget;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfileCompletionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();

        $requiredFields = [
            'first_name'  => __('Nama Depan'),
            'last_name'   => __('Nama Belakang'),
            'whatsapp'    => __('WhatsApp'),
            'gender'      => __('Jenis Kelamin'),
            'address'     => __('Alamat'),
            'occupation'  => __('Pekerjaan'),
        ];

        $filled = 0;
        $missing = [];

        foreach ($requiredFields as $field => $label) {
            if (!empty($user->{$field})) {
                $filled++;
            } else {
                $missing[] = $label;
            }
        }

        if ($user?->identity_type) {
            $filled++;
        } else {
            $missing[] = __('Identitas');
        }

        $total = count($requiredFields) + 1;
        $percentage = $total > 0 ? round(($filled / $total) * 100) : 0;

        $color = $percentage >= 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');

        return [
            Stat::make(__('Kelengkapan Profil'), $percentage . '%')
                ->description($percentage >= 100
                    ? __('Profil Anda sudah lengkap!')
                    : __('Masih kurang: ') . implode(', ', $missing))
                ->descriptionIcon($percentage >= 100 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle')
                ->color($color)
                ->chart([min(100, $percentage), max(0, $percentage)]),
        ];
    }
}
