<?php

namespace App\Http\Controllers\Api\Admin\AnalyticsController;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent\SecurityEvent;
use App\Support\AdminAccess\AdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(AdminAccess::allows('view_analytics'), 403);

        $days = min(max((int) $request->integer('days', 30), 1), 365);
        $since = now()->subDays($days - 1)->startOfDay();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['days' => $days, 'from' => $since->toIso8601String(), 'to' => now()->toIso8601String()],
                'users' => [
                    'total' => DB::table('users')->count(),
                    'new' => DB::table('users')->where('created_at', '>=', $since)->count(),
                ],
                'orders' => [
                    'total' => DB::table('orders')->where('created_at', '>=', $since)->count(),
                    'by_day' => $this->groupByDay('orders', $since),
                ],
                'transactions' => [
                    'total' => DB::table('transactions')->where('created_at', '>=', $since)->count(),
                    'by_day' => $this->groupByDay('transactions', $since),
                ],
                'security' => [
                    'blocked_requests' => SecurityEvent::query()->where('event', 'blocked_request')->where('created_at', '>=', $since)->count(),
                    'by_status' => SecurityEvent::query()->where('created_at', '>=', $since)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
                    'recent' => SecurityEvent::query()->latest()->limit(20)->get(['event', 'method', 'path', 'status', 'ip_address', 'created_at']),
                ],
            ],
        ]);
    }

    public function emergencyStatus(): JsonResponse
    {
        abort_unless(AdminAccess::allows('manage_security'), 403);

        return response()->json([
            'status' => 'success',
            'data' => [
                'lockdown' => (bool) config('security.emergency_lockdown'),
                'backup_disk' => config('backup.backup.destination.disks', ['local']),
                'last_backup' => $this->lastBackupTimestamp(),
            ],
        ]);
    }

    private function groupByDay(string $table, mixed $since): array
    {
        return DB::table($table)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')->orderBy('date')->get()->mapWithKeys(fn ($row) => [$row->date => (int) $row->total])->all();
    }

    private function lastBackupTimestamp(): ?string
    {
        $directory = storage_path('app/'.config('backup.backup.destination.disks.0', 'local'));
        if (! is_dir($directory)) {
            return null;
        }

        $files = glob($directory.'/*') ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return date(DATE_ATOM, filemtime($files[0]));
    }
}
