<?php

namespace App\Http\Controllers\Api\Admin\DashboardController;

use App\Http\Controllers\Controller;
use App\Models\Category\Category;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Tren harian 10 hari terakhir (termasuk hari ini).
     *
     * @return array<int> gauge 0 = hari ke-9 lalu, 9 = hari ini
     */
    private function dailyTrend(string $model, ?string $sumColumn = null): array
    {
        $query = $model::query()->where('created_at', '>=', now()->subDays(10)->startOfDay());

        $rows = $sumColumn
            ? $query->selectRaw('date(created_at) as date, sum('.$sumColumn.') as sum')->groupBy('date')
            : $query->selectRaw('date(created_at) as date, count(*) as count')->groupBy('date');

        $data = $rows->pluck($sumColumn ? 'sum' : 'count', 'date')->toArray();

        return collect(range(9, 0))
            ->map(function ($days) use ($data): float {
                $value = $data[now()->subDays($days)->format('Y-m-d')] ?? 0;

                return (float) $value;
            })
            ->values()
            ->all();
    }

    /**
     * Seri bulanan 6 bulan terakhir.
     */
    private function monthlySeries(string $model, ?string $sumColumn = null): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite' ? 'strftime("%m", created_at)' : 'MONTH(created_at)';

        $query = $model::query()->where('created_at', '>=', now()->subMonths(6)->startOfMonth());
        $query = $sumColumn
            ? $query->selectRaw("{$monthExpr} as month, sum({$sumColumn}) as value")->groupBy('month')
            : $query->selectRaw("{$monthExpr} as month, count(*) as value")->groupBy('month');

        $data = $query->pluck('value', 'month')->toArray();

        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (float) ($data[$month->format('m')] ?? $data[(int) $month->format('m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $values];
    }

    public function index(): JsonResponse
    {
        try {
            $totalUsers = User::count();
            $totalPackages = Package::count();
            $totalProducts = Product::count();
            $totalOrders = Order::count();
            $totalVouchers = Voucher::count();
            $totalCategories = Category::count();
            $totalRevenue = Order::where('payment_status', 'paid')->sum('total_price');
            $thisMonthRevenue = Order::where('payment_status', 'paid')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_price');

            $newUsers = User::where('created_at', '>=', now()->subDays(7))->count();
            $newOrders = Order::where('created_at', '>=', now()->subDays(7))->count();
            $newPackages = Package::where('created_at', '>=', now()->subDays(7))->count();
            $newProducts = Product::where('created_at', '>=', now()->subDays(7))->count();

            $recentOrders = Order::with('user:id,full_name,email')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'user_name' => $o->user?->full_name,
                    'total_price' => $o->total_price,
                    'status' => $o->status->value,
                    'created_at' => $o->created_at?->toISOString(),
                ]);

            $ordersByStatus = [
                'pending' => Order::where('status', 'pending')->count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'processing' => Order::where('status', 'processing')->count(),
                'completed' => Order::where('status', 'completed')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ];

            $kycCounts = [
                'pending' => User::whereNull('kyc_status')
                    ->where(fn ($q) => $q
                        ->whereNotNull('ktp_photo')
                        ->orWhereNotNull('selfie_photo')
                        ->orWhereNotNull('face_scan_photo'))
                    ->count(),
                'verified' => User::where('kyc_status', 'verified')->count(),
                'rejected' => User::where('kyc_status', 'rejected')->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    // Totals
                    'total_users' => $totalUsers,
                    'total_packages' => $totalPackages,
                    'total_products' => $totalProducts,
                    'total_categories' => $totalCategories,
                    'total_orders' => $totalOrders,
                    'total_vouchers' => $totalVouchers,
                    'total_revenue' => $totalRevenue,
                    // Growth (7 hari)
                    'new_users_7d' => $newUsers,
                    'new_orders_7d' => $newOrders,
                    'new_packages_7d' => $newPackages,
                    'new_products_7d' => $newProducts,
                    'total_revenue_month' => $thisMonthRevenue,
                    // Sparklines (10 hari, termasuk hari ini)
                    'sparklines' => [
                        'users' => $this->dailyTrend(User::class),
                        'orders' => $this->dailyTrend(Order::class),
                        'packages' => $this->dailyTrend(Package::class),
                        'products' => $this->dailyTrend(Product::class),
                        'revenue' => $this->dailyTrend(Order::class, 'total_price'),
                    ],
                    // Chart bulanan (6 bulan)
                    'monthly' => [
                        'revenue' => $this->monthlySeries(Order::class, 'total_price'),
                        'orders' => $this->monthlySeries(Order::class),
                    ],
                    // Lists
                    'recent_orders' => $recentOrders,
                    'orders_by_status' => $ordersByStatus,
                    'kyc_counts' => $kycCounts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memuat dashboard'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
