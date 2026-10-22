<?php

namespace App\Filament\Admin\Widgets\RevenueChart;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * Grafik pendapatan 7 hari terakhir.
 */
class RevenueChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';
    /**
     * Widget Filament LAZY secara bawaan: isinya diambil lewat request
     * Livewire terpisah saat widget masuk viewport, dan HTML awal cuma
     * berisi skeleton. Untuk dashboard admin, angkanya harus LANGSUNG
     * terlihat -- jadi lazy dimatikan.
     */
    protected static bool $isLazy = false;

    /**
     * Tinggi maksimum grafik.
     *
     * Tanpa ini, canvas-nya ikut tinggi widget -- di dashboard yang widgetnya
     * cuma separuh lebar, grafiknya jadi immense dan isinya cuma garis datar
     * di tengah-tengah ruang kosong.
     */
    protected static ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return __('Pendapatan 7 Hari Terakhir');
    }

    protected function getData(): array
    {
        $rows = Cache::remember('admin_widget_revenue_chart', 60, function (): array {
            $data = [];

            for ($day = 6; $day >= 0; $day--) {
                $date = now()->subDays($day);

                $data[] = [
                    'tanggal' => $date->format('d M'),
                    'pendapatan' => (float) Order::query()
                        ->where('payment_status', 'paid')
                        ->whereDate('created_at', $date->toDateString())
                        ->sum('total_price'),
                ];
            }

            return $data;
        });

        return [
            'datasets' => [
                [
                    'label' => __('Pendapatan'),
                    'data' => collect($rows)->pluck('pendapatan')->all(),
                ],
            ],
            'labels' => collect($rows)->pluck('tanggal')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
