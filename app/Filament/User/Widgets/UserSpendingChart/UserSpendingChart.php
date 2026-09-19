<?php

namespace App\Filament\User\Widgets\UserSpendingChart;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserSpendingChart extends ChartWidget
{
    protected static ?int $navigationSort = 3;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    public function getHeading(): string|Htmlable
    {
        return __('Pengeluaran Saya (6 Bulan Terakhir)');
    }

    protected function getData(): array
    {
        $userId = Auth::id();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite' ? 'strftime("%m", created_at)' : 'MONTH(created_at)';

        $data = Order::query()
            ->where('user_id', $userId)
            ->where('payment_status', OrderPaymentStatus::PAID)
            ->selectRaw("{$monthExpr} as month, SUM(total_price) as sum")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderByRaw('MIN(created_at) ASC', [])
            ->pluck('sum', 'month')
            ->toArray();

        $labels = [];
        $spending = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('m');
            $labels[] = now()->subMonths($i)->translatedFormat('M');
            $val = $data[$month] ?? $data[(int) $month] ?? 0;
            $spending[] = (float) $val;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Total Pengeluaran (IDR)'),
                    'data' => $spending,
                    'fill' => 'start',
                    'tension' => 0.4,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => 'rgb(16, 185, 129)',
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
