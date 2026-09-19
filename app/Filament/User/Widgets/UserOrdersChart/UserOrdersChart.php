<?php

namespace App\Filament\User\Widgets\UserOrdersChart;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserOrdersChart extends ChartWidget
{
    protected static ?int $navigationSort = 2;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public function getHeading(): string|Htmlable
    {
        return __('Aktivitas Pesanan (6 Bulan Terakhir)');
    }

    protected function getData(): array
    {
        $userId = Auth::id();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite' ? 'strftime("%m", created_at)' : 'MONTH(created_at)';

        $data = Order::query()
            ->where('user_id', $userId)
            ->selectRaw("{$monthExpr} as month, count(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderByRaw('MIN(created_at) ASC', [])
            ->pluck('count', 'month')
            ->toArray();

        $labels = [];
        $counts = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('m');
            $labels[] = now()->subMonths($i)->translatedFormat('M');
            $val = $data[$month] ?? $data[(int) $month] ?? 0;
            $counts[] = $val;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Total Pesanan'),
                    'data' => $counts,
                    'fill' => 'start',
                    'tension' => 0.4,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'borderColor' => 'rgb(99, 102, 241)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
