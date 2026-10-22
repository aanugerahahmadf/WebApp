<?php

namespace App\Filament\Admin\Widgets\OrdersChart;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * Grafik jumlah pesanan 7 hari terakhir.
 */
class OrdersChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';
    /**
     * Widget Filament LAZY secara bawaan: isinya diambil lewat request
     * Livewire terpisah saat widget masuk viewport, dan HTML awal cuma
     * berisi skeleton. Untuk dashboard admin, angkanya harus LANGSUNG
     * terlihat -- jadi lazy dimatikan.
     */
    protected static bool $isLazy = false;

    protected static ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return __('Pesanan 7 Hari Terakhir');
    }

    protected function getData(): array
    {
        $rows = Cache::remember('admin_widget_orders_chart', 60, function (): array {
            $data = [];

            for ($day = 6; $day >= 0; $day--) {
                $date = now()->subDays($day);

                $data[] = [
                    'tanggal' => $date->format('d M'),
                    'pesanan' => Order::query()
                        ->whereDate('created_at', $date->toDateString())
                        ->count(),
                ];
            }

            return $data;
        });

        return [
            'datasets' => [
                [
                    'label' => __('Pesanan'),
                    'data' => collect($rows)->pluck('pesanan')->all(),
                ],
            ],
            'labels' => collect($rows)->pluck('tanggal')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
