<?php

namespace App\Filament\Admin\Widgets\RevenueChart;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RevenueChart extends ChartWidget
{
    protected static ?string $pollingInterval = null; // Disable mobile polling to avoid method-not-found crashes

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable
    {
        return __('Tren Pendapatan');
    }

    protected function getData(): array
    {
        return Cache::remember('revenue_chart_data', now()->addMinutes(10), function () {
            // Deteksi cerdas: Gunakan sintaks spesifik database
            $driver = DB::connection()->getDriverName();
            $monthExpr = $driver === 'sqlite' ? 'strftime("%m", created_at)' : 'MONTH(created_at)';

            /** @var Builder $query */
            $query = Order::query();

            $data = $query->where('payment_status', 'paid')
                ->selectRaw("{$monthExpr} as month, SUM(total_price) as sum")
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('sum', 'month')
                ->toArray();

            $labels = [];
            $revenue = [];

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i)->format('m');
                $labels[] = ucfirst(now()->subMonths($i)->translatedFormat('M'));

                // Handle key mismatch (some DBs return '01', some return 1)
                $val = $data[$month] ?? $data[(int) $month] ?? 0;
                $revenue[] = (float) $val;
            }

            return [
                'datasets' => [
                    [
                        'label' => __('Pendapatan (IDR)'),
                        'data' => $revenue,
                        'backgroundColor' => 'rgba(233, 30, 99, 0.1)',
                        'borderColor' => '#E91E63',
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
        // Explicitly defined to fix "MethodNotFoundException" on some mobile environments
        $this->cachedData = null;
        $this->dispatch('updateChartData', [
            'data' => $this->getData(),
        ]);
    }
}
