<?php

namespace App\Filament\Admin\Widgets\OrdersChart;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrdersChart extends ChartWidget
{
    protected static ?string $pollingInterval = null; // Disable mobile polling

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable
    {
        return __('Jumlah Pesanan');
    }

    protected function getData(): array
    {
        return Cache::remember('orders_chart_data', now()->addMinutes(10), function () {
            // Deteksi cerdas: Gunakan sintaks spesifik database
            $driver = DB::connection()->getDriverName();
            $monthExpr = $driver === 'sqlite' ? 'strftime("%m", created_at)' : 'MONTH(created_at)';

            /** @var Builder $query */
            $query = Order::query();

            $data = $query->selectRaw("{$monthExpr} as month, count(*) as count")
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('count', 'month')
                ->toArray();

            $labels = [];
            $counts = [];

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i)->format('m');
                $labels[] = now()->subMonths($i)->translatedFormat('M');

                // Handle key mismatch (some DBs return '01', some return 1)
                $val = $data[$month] ?? $data[(int) $month] ?? 0;
                $counts[] = $val;
            }

            return [
                'datasets' => [
                    [
                        'label' => __('Pesanan'),
                        'data' => $counts,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'fill' => 'start',
                        'tension' => 0.4,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 10,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function updateChartData(): void
    {
        $this->cachedData = null;
        $this->dispatch('updateChartData', [
            'data' => $this->getData(),
        ]);
    }
}
