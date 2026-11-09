<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\SqlExpression\SqlExpression;
use Illuminate\Support\Facades\DB;

echo 'driver = '.DB::connection()->getDriverName().PHP_EOL;

$run = function (string $label, callable $fn): void {
    try {
        $result = $fn();
        echo 'OK   '.$label.' => '.json_encode($result).PHP_EOL;
    } catch (Throwable $e) {
        echo 'FAIL '.$label.' => '.$e->getMessage().PHP_EOL;
    }
};

$driver = DB::connection()->getDriverName();

$monthExpr = match ($driver) {
    'sqlite' => 'strftime("%m", created_at)',
    'pgsql' => 'EXTRACT(MONTH FROM created_at)',
    default => 'MONTH(created_at)',
};

$dateExpr = $driver === 'pgsql' ? 'CAST(created_at AS date)' : 'date(created_at)';

// Cermin persis cabang driver di MessagesPage / Inbox, bukan hardcode pgsql.
$unread = function ($query, string $id) use ($driver): void {
    if ($driver === 'sqlite') {
        $query->whereRaw('read_by NOT LIKE ?', ['%"'.$id.'"%']);
    } elseif ($driver === 'pgsql') {
        $query->whereRaw("NOT (COALESCE(read_by::jsonb, '[]'::jsonb) @> to_jsonb(?::text))", [$id]);
    } else {
        $query->whereRaw('JSON_SEARCH(read_by, "one", ?) IS NULL', [$id]);
    }
};

$arrayLength = fn ($query, int $n) => $query->whereRaw(
    $driver === 'pgsql' ? 'jsonb_array_length(user_ids::jsonb) = ?' : 'JSON_LENGTH(user_ids) = ?',
    [$n]
);

$run('monthlySeries', fn () => DB::table('orders')
    ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
    ->selectRaw("{$monthExpr} as month, count(*) as value")
    ->groupBy('month')
    ->pluck('value', 'month')
    ->toArray());

$run('dailyTrend', fn () => DB::table('orders')
    ->where('created_at', '>=', now()->subDays(10)->startOfDay())
    ->selectRaw("{$dateExpr} as date, count(*) as count")
    ->groupBy('date')
    ->pluck('count', 'date')
    ->toArray());

$run('groupByDay(security_events)', fn () => DB::table('security_events')
    ->where('created_at', '>=', now()->subDays(30))
    ->select(DB::raw("{$dateExpr} as date"), DB::raw('COUNT(*) as total'))
    ->groupBy('date')->orderBy('date')->get()
    ->mapWithKeys(fn ($row) => [$row->date => (int) $row->total])->all());

$run('unread badge (admin)', function () use ($unread) {
    $query = DB::table('fm_messages');
    $unread($query, '1');

    return $query->count();
});

$run('unread badge (user)', function () use ($unread) {
    $query = DB::table('fm_messages');
    $unread($query, '7');

    return $query->count();
});

$run('inbox lookup (array length)', function () use ($arrayLength) {
    $query = DB::table('fm_inboxes');
    $arrayLength($query, 2);

    return $query->count();
});

$run('cbir reorder (SqlExpression::orderByIds)', fn () => DB::table('catalogs')
    ->whereIn('id', [3, 1, 2])
    ->orderByRaw(SqlExpression::orderByIds([3, 1, 2]))
    ->pluck('id')
    ->toArray());
