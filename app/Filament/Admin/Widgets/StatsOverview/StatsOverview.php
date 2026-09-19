<?php

namespace App\Filament\Admin\Widgets\StatsOverview;

use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $data = Cache::remember('stats_overview_data_v2', now()->addMinutes(5), function () {
            // Helper to get trend data for the last 10 days
            $getTrend = function ($model) {
                /** @var Builder $query */
                $query = $model::query()->where('created_at', '>=', now()->subDays(10));

                $data = $query->selectRaw('date(created_at) as date, count(*) as count')
                    ->groupBy('date')
                    ->pluck('count', 'date')
                    ->toArray();

                return collect(range(9, 0))
                    ->map(fn ($days) => $data[now()->subDays($days)->format('Y-m-d')] ?? 0)
                    ->toArray();
            };

            $userCounts = $getTrend(User::class);
            $orderCounts = $getTrend(Order::class);
            $packageCounts = $getTrend(Package::class);
            $productCounts = $getTrend(Product::class);

            // Calculate Revenue Trend (Last 10 days vs previous 10 days)
            /** @var Builder $query */
            $query = Order::where('payment_status', 'paid');

            $revenueData = $query->where('created_at', '>=', now()->subDays(10))
                ->selectRaw('date(created_at) as date, sum(total_price) as sum')
                ->groupBy('date')
                ->pluck('sum', 'date')
                ->toArray();

            $revenueCounts = collect(range(9, 0))
                ->map(fn ($days) => (float) ($revenueData[now()->subDays($days)->format('Y-m-d')] ?? 0))
                ->toArray();

            /** @var Builder $revenueQuery */
            $revenueQuery = Order::where('payment_status', 'paid');
            $totalRevenue = $revenueQuery->sum('total_price');

            /** @var Builder $monthRevenueQuery */
            $monthRevenueQuery = Order::where('payment_status', 'paid');
            $thisMonthRevenue = $monthRevenueQuery->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_price');

            // Growth indicators
            /** @var Builder $newUserQuery */
            $newUserQuery = User::where('created_at', '>=', now()->subDays(7));
            $newUserCount = $newUserQuery->count();

            /** @var Builder $newOrderQuery */
            $newOrderQuery = Order::where('created_at', '>=', now()->subDays(7));
            $newOrderCount = $newOrderQuery->count();

            /** @var Builder $newPackageQuery */
            $newPackageQuery = Package::where('created_at', '>=', now()->subDays(7));
            $newPackageCount = $newPackageQuery->count();

            /** @var Builder $newProductQuery */
            $newProductQuery = Product::where('created_at', '>=', now()->subDays(7));
            $newProductCount = $newProductQuery->count();

            return [
                'userCounts' => $userCounts,
                'orderCounts' => $orderCounts,
                'packageCounts' => $packageCounts,
                'productCounts' => $productCounts,
                'revenueCounts' => $revenueCounts,
                'totalUsers' => User::query()->count(),
                'totalOrders' => Order::query()->count(),
                'totalPackages' => Package::query()->count(),
                'totalProducts' => Product::query()->count(),
                'totalRevenue' => $totalRevenue,
                'thisMonthRevenue' => $thisMonthRevenue,
                'newUserCount' => $newUserCount,
                'newOrderCount' => $newOrderCount,
                'newPackageCount' => $newPackageCount,
                'newProductCount' => $newProductCount,
            ];
        });

        return [
            Stat::make(__('Total Pengguna'), (string) $data['totalUsers'])
                ->description($data['newUserCount'].' '.__('baru minggu ini'))
                ->descriptionIcon($data['newUserCount'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['userCounts'])
                ->color($data['totalUsers'] > 0 ? 'info' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-300',
                    'onclick' => "window.location.href='".route('filament.admin.resources.users.index')."'",
                ]),

            Stat::make(__('Total Pesanan'), (string) $data['totalOrders'])
                ->description($data['newOrderCount'].' '.__('baru minggu ini'))
                ->descriptionIcon($data['newOrderCount'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['orderCounts'])
                ->color($data['totalOrders'] > 0 ? 'warning' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-300',
                    'onclick' => "window.location.href='".route('filament.admin.resources.orders.index')."'",
                ]),

            Stat::make(__('Total Paket'), (string) $data['totalPackages'])
                ->description($data['newPackageCount'].' '.__('baru minggu ini'))
                ->descriptionIcon($data['newPackageCount'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['packageCounts'])
                ->color($data['totalPackages'] > 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-300',
                    'onclick' => "window.location.href='".route('filament.admin.resources.packages.index')."'",
                ]),

            Stat::make(__('Total Produk'), (string) $data['totalProducts'])
                ->description($data['newProductCount'].' '.__('baru minggu ini'))
                ->descriptionIcon($data['newProductCount'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['productCounts'])
                ->color($data['totalProducts'] > 0 ? 'purple' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-300',
                    'onclick' => "window.location.href='".route('filament.admin.resources.products.index')."'",
                ]),

            Stat::make(__('Total Pendapatan'), 'Rp '.number_format($data['totalRevenue'], 0, ',', '.'))
                ->description('Rp '.number_format($data['thisMonthRevenue'], 0, ',', '.').' '.__('bulan ini'))
                ->descriptionIcon($data['thisMonthRevenue'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['revenueCounts'])
                ->color($data['totalRevenue'] > 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-300',
                    'onclick' => "window.location.href='".route('filament.admin.resources.transactions.index')."'",
                ]),
        ];
    }
}
