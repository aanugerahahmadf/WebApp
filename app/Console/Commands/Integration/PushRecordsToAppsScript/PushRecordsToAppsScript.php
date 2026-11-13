<?php

namespace App\Console\Commands\Integration\PushRecordsToAppsScript;

use App\Services\GoogleAppsScript\AppsScriptException;
use App\Services\GoogleAppsScript\AppsScriptSignature;
use App\Services\GoogleAppsScript\GoogleAppsScriptClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Salin data utama ke Google Apps Script (lalu ke Google Spreadsheet).
 *
 *   php artisan apps-script:sync orders
 *   php artisan apps-script:sync orders --since=2026-11-01 --limit=500
 *   php artisan apps-script:sync all --dry-run
 *   php artisan apps-script:sync health
 *
 * Perilaku:
 *  - `--dry-run` tidak butuh kredensial dan mencetak payload + canonical string,
 *    persis bentuk yang ditandatangani, supaya bisa dicocokkan dengan aksi
 *    `auth.sampleSignature` di Apps Script.
 *  - Kolom yang tidak ada di database dilewati (bukan error), jadi perintah ini
 *    tetap jalan pada skema lama maupun baru.
 *  - Harga, status, dan tanggal dibaca dari kolom sumber yang benar
 *    (`transactions.total_amount` lebih dulu, lihat AGENTS.md §5.5).
 */
class PushRecordsToAppsScript extends Command
{
    protected $signature = 'apps-script:sync
                            {module=orders : orders|reviews|catalog|kyc|cbir|db.<table>|all|all-db|health}
                            {--since= : Hanya record dibuat/ubah sejak tanggal ini (Y-m-d)}
                            {--limit=1000 : Jumlah baris maksimum per modul}
                            {--chunk=200 : Baris per permintaan Apps Script}
                            {--dry-run : Cetak payload, jangan kirim}';

    protected $description = 'Sinkronkan salinan data ke Google Apps Script (modul utama + seluruh tabel DB yang aman)';

    /** Modul inti registry khusus. */
    protected const MODULES = ['orders', 'reviews', 'catalog', 'kyc', 'cbir'];

    /** Tabel yang tidak pernah disinkron (secret/ephemeral/internal). */
    protected const EXCLUDED_TABLES = [
        'admin_password_reset_tokens', 'password_reset_tokens', 'personal_access_tokens',
        'backup_codes', 'sessions', 'breezy_sessions', 'user_sessions', 'whatsapp_otps',
        'device_tokens', 'trusted_devices', 'trust_devices', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'migrations', 'fm_messages', 'fm_inboxes',
        'chat_calls',
    ];

    /** Kolom sensitif yang dibuang dari sinkron tabel mentah. */
    protected const SENSITIVE_COLUMN_PATTERN = '/(password|remember_token|^token$|_token$|two_factor_secret|two_factor_recovery_codes|otp_code|otp_purpose|otp_expires_at|fcm_token|pin_hash)/i';

    public function handle(): int
    {
        $module = strtolower((string) $this->argument('module'));
        $dryRun = (bool) $this->option('dry-run');
        $client = GoogleAppsScriptClient::make();

        if ($module === 'health') {
            return $this->reportHealth($client);
        }

        if (! $dryRun && ! $client->isConfigured()) {
            $this->components->error('Google Apps Script belum dikonfigurasi.');
            $this->components->twoColumnDetail('GOOGLE_APPS_SCRIPT_EXEC_URL', config('services.google_apps_script.exec_url') ?: '(kosong)');
            $this->components->twoColumnDetail('GOOGLE_APPS_SCRIPT_SECRET', config('services.google_apps_script.secret') ? '(ada)' : '(kosong)');
            $this->newLine();
            $this->line('Deploy dulu lewat apps_script/README.md, lalu isi kedua nilai di .env dan jalankan: php artisan optimize:clear');

            return self::FAILURE;
        }

        if ($module === 'all') {
            $modules = array_merge(self::MODULES, $this->allDbModules());
        } elseif ($module === 'all-db') {
            $modules = $this->allDbModules();
        } elseif (str_starts_with($module, 'db.')) {
            $modules = [$module];
        } else {
            $modules = [$module];
        }

        if ($module !== 'all' && $module !== 'all-db' && ! str_starts_with($module, 'db.') && ! in_array($module, self::MODULES, true)) {
            $this->components->error("Modul `$module` tidak dikenal. Pilihan: ".implode(', ', self::MODULES).', db.<table>, health, all, all-db.');

            return self::FAILURE;
        }

        $exit = self::SUCCESS;

        foreach ($modules as $name) {
            $exit = $this->syncModule($name, $client, $dryRun) ? $exit : self::FAILURE;
        }

        return $exit;
    }

    protected function reportHealth(GoogleAppsScriptClient $client): int
    {
        $health = $client->health();

        if (($health['ok'] ?? false) === false) {
            $this->components->error('Apps Script tidak terjangkau: '.($health['code'] ?? $health['message'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->components->info('Apps Script hidup: '.$health['service'].' '.$health['version']);
        $this->components->twoColumnDetail('Waktu server', (string) $health['time']);
        $this->components->twoColumnDetail('Spreadsheet terhubung', $health['spreadsheetConfigured'] ? 'ya' : 'BELUM');
        $this->components->twoColumnDetail('Secret cocok', $health['secretConfigured'] ? 'ya' : 'BELUM');
        $this->components->twoColumnDetail('Tab', implode(', ', $health['tabs'] ?? []));

        return self::SUCCESS;
    }

    protected function syncModule(string $module, GoogleAppsScriptClient $client, bool $dryRun): bool
    {
        $rows = $this->records($module);

        if ($rows->isEmpty()) {
            $this->components->warn("[$module] tidak ada data untuk disinkron.");

            return true;
        }

        $action = match ($module) {
            'orders' => 'orders.rows',
            'reviews' => 'reviews.rows',
            'catalog' => 'catalog.rows',
            'kyc' => 'kyc.events',
            'cbir' => 'cbir.searches',
            default => str_starts_with($module, 'db.') ? $module : $module,
        };

        $this->components->info("[$module] {$rows->count()} baris -> $action");

        $failed = 0;

        foreach (GoogleAppsScriptClient::chunks($rows->all()) as $chunk) {
            if ($dryRun) {
                $this->printDryRun($action, $chunk);

                continue;
            }

            try {
                $result = $client->append($action, $chunk);
                $this->line(sprintf(
                    '  dikirim %s baris ke tab %s',
                    (string) ($result['written'] ?? count($chunk)),
                    (string) ($result['tab'] ?? '?'),
                ));
            } catch (AppsScriptException $exception) {
                $failed++;
                $this->components->error('  ditolak: ['.$exception->errorCode().'] '.$exception->getMessage());

                if ($exception->isConfigurationProblem() || $exception->isSignatureProblem()) {
                    break;
                }
            } catch (Throwable $throwable) {
                $failed++;
                $this->components->error('  gagal: '.$throwable->getMessage());
            }
        }

        return $failed === 0;
    }

    /**
     * Tampilkan persis apa yang akan ditandatangani. Kalau hasil lokal dan
     * `auth.sampleSignature` berbeda, penyebabnya selalu di sini.
     */
    protected function printDryRun(string $action, array $chunk): void
    {
        $timestamp = now()->timestamp;
        $nonce = 'dry-run';

        $this->line('  action    : '.$action.'.append');
        $this->line('  records   : '.count($chunk));
        $this->line('  canonical : '.substr(AppsScriptSignature::canonicalString(
            $timestamp,
            $nonce,
            $action.'.append',
            ['records' => $chunk],
        ), 0, 240).'…');
        $this->line('  sample    : '.json_encode($chunk[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int,string>
     */
    protected function allDbModules(): array
    {
        try {
            $tables = Schema::getTableListing();
        } catch (Throwable) {
            return [];
        }

        $modules = [];

        foreach ($tables as $table) {
            $name = is_array($table) ? (string) ($table['name'] ?? reset($table)) : (string) $table;
            // Schema::getTableListing() may return schema-qualified names on some drivers.
            if (str_contains($name, '.')) {
                $name = (string) substr($name, strrpos($name, '.') + 1);
            }
            if ($name === '' || in_array($name, self::EXCLUDED_TABLES, true)) {
                continue;
            }
            $modules[] = 'db.'.$name;
        }

        sort($modules);

        return $modules;
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    protected function records(string $module): Collection
    {
        $limit = max(1, (int) $this->option('limit'));
        $since = $this->option('since') ? (string) $this->option('since') : null;

        if (str_starts_with($module, 'db.')) {
            return $this->genericTableRecords(substr($module, 3), $limit, $since);
        }

        return match ($module) {
            'orders' => $this->orderRecords($limit, $since),
            'reviews' => $this->reviewRecords($limit, $since),
            'catalog' => $this->catalogRecords($limit, $since),
            'kyc' => $this->kycRecords($limit, $since),
            'cbir' => $this->cbirRecords($limit, $since),
            default => new Collection,
        };
    }

    /**
     * Sync generik untuk tabel DB -> tab `db.<table>`.
     *
     * @return Collection<int,array<string,mixed>>
     */
    protected function genericTableRecords(string $table, int $limit, ?string $since): Collection
    {
        if (in_array($table, self::EXCLUDED_TABLES, true) || ! Schema::hasTable($table)) {
            return new Collection;
        }

        $columns = array_values(array_filter(
            Schema::getColumnListing($table),
            fn (string $column) => ! (bool) preg_match(self::SENSITIVE_COLUMN_PATTERN, $column),
        ));

        if ($columns === []) {
            return new Collection;
        }

        $query = DB::table($table)->select($columns);

        if ($since !== null) {
            $dateCol = in_array('updated_at', $columns, true) ? 'updated_at'
                : (in_array('created_at', $columns, true) ? 'created_at' : null);
            if ($dateCol !== null) {
                $query->where($dateCol, '>=', $since);
            }
        }

        $orderCol = in_array('id', $columns, true) ? 'id'
            : (in_array('created_at', $columns, true) ? 'created_at' : null);
        if ($orderCol !== null) {
            $query->orderByDesc($orderCol);
        }

        $rows = $query->limit($limit)->get();

        return $rows->map(function ($row) use ($columns) {
            $record = [];
            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null) {
                    continue;
                }
                if (is_bool($value)) {
                    $record[$column] = $value ? '1' : '0';
                } elseif (is_array($value) || is_object($value)) {
                    $record[$column] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $record[$column] = (string) $value;
                }
            }
            $record['source'] = GoogleAppsScriptClient::SOURCE;
            if (! array_key_exists('observed_at', $record) || $record['observed_at'] === '') {
                $record['observed_at'] = (string) ($row->updated_at ?? $row->created_at ?? now()->toISOString());
            }

            return $record;
        });
    }

    protected function orderRecords(int $limit, ?string $since): Collection
    {
        if (! Schema::hasTable('orders')) {
            return new Collection;
        }

        $rows = DB::table('orders')
            ->select($this->existingColumns('orders', [
                'id', 'order_number', 'user_id', 'catalog_id', 'quantity', 'total_price',
                'status', 'payment_status', 'booking_date', 'booking_time', 'notes',
                'virtual_account_no', 'virtual_account_expiry', 'qr_content', 'qr_expiry',
                'created_at', 'updated_at',
            ]))
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($order) => array_filter([
            'order_id' => (string) $order->id,
            'order_number' => (string) ($order->order_number ?? ''),
            'user_id' => isset($order->user_id) ? (string) $order->user_id : null,
            'quantity' => isset($order->quantity) ? (string) $order->quantity : null,
            // Nominal sebagai STRING: 150000 vs 150000.0 berbeda antar bahasa.
            'total_amount' => isset($order->total_price) ? number_format((float) $order->total_price, 0, '.', '') : null,
            'status' => (string) ($order->status ?? ''),
            'payment_status' => (string) ($order->payment_status ?? ''),
            'va_number' => isset($order->virtual_account_no) ? (string) $order->virtual_account_no : null,
            'qris_payload' => isset($order->qr_content) ? (string) $order->qr_content : null,
            'payment_deadline' => (string) ($order->virtual_account_expiry ?? $order->qr_expiry ?? ''),
            'event_date' => (string) ($order->booking_date ?? ''),
            'event_time' => (string) ($order->booking_time ?? ''),
            'notes' => (string) ($order->notes ?? ''),
            'observed_at' => isset($order->updated_at) ? (string) $order->updated_at : null,
            'source' => GoogleAppsScriptClient::SOURCE,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    protected function reviewRecords(int $limit, ?string $since): Collection
    {
        if (! Schema::hasTable('reviews')) {
            return new Collection;
        }

        $rows = DB::table('reviews')
            ->select($this->existingColumns('reviews', [
                'id', 'user_id', 'catalog_id', 'rating', 'title', 'comment',
                'photo', 'helpful_count', 'created_at',
            ]))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($review) => array_filter([
            'review_id' => (string) $review->id,
            'catalog_id' => isset($review->catalog_id) ? (string) $review->catalog_id : null,
            'user_id' => isset($review->user_id) ? (string) $review->user_id : null,
            'rating' => isset($review->rating) ? (string) $review->rating : null,
            'title' => (string) ($review->title ?? ''),
            'comment' => (string) ($review->comment ?? ''),
            'has_media' => empty($review->photo) ? '0' : '1',
            'media_paths' => (string) ($review->photo ?? ''),
            'observed_at' => isset($review->created_at) ? (string) $review->created_at : null,
            'source' => GoogleAppsScriptClient::SOURCE,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    protected function catalogRecords(int $limit, ?string $since): Collection
    {
        if (! Schema::hasTable('catalogs')) {
            return new Collection;
        }

        $rows = DB::table('catalogs')
            ->select($this->existingColumns('catalogs', [
                'id', 'name', 'name_en', 'category_id', 'price', 'discount_price',
                'stock', 'rating_average', 'rating_count', 'is_featured', 'image', 'created_at',
            ]))
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($catalog) => array_filter([
            'catalog_id' => (string) $catalog->id,
            'name' => (string) ($catalog->name ?? ''),
            'category' => isset($catalog->category_id) ? (string) $catalog->category_id : null,
            'price' => isset($catalog->price) ? number_format((float) $catalog->price, 0, '.', '') : null,
            'sale_price' => isset($catalog->discount_price) ? number_format((float) $catalog->discount_price, 0, '.', '') : null,
            'stock' => isset($catalog->stock) ? (string) $catalog->stock : null,
            'rating_average' => isset($catalog->rating_average) ? (string) $catalog->rating_average : null,
            'rating_count' => isset($catalog->rating_count) ? (string) $catalog->rating_count : null,
            'is_featured' => ! empty($catalog->is_featured) ? '1' : '0',
            'media_url' => (string) ($catalog->image ?? ''),
            'currency' => 'IDR',
            'source' => GoogleAppsScriptClient::SOURCE,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    protected function kycRecords(int $limit, ?string $since): Collection
    {
        if (! Schema::hasTable('identity_documents')) {
            return new Collection;
        }

        $rows = DB::table('identity_documents')
            ->select($this->existingColumns('identity_documents', [
                'id', 'user_id', 'document_type', 'ai_document_type', 'ai_reason',
                'ai_reason_code', 'ai_blocking_issue', 'ai_score', 'ai_verified', 'created_at',
            ]))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($document) => array_filter([
            'event_id' => (string) $document->id,
            'user_id' => isset($document->user_id) ? (string) $document->user_id : null,
            'document_type' => (string) ($document->document_type ?? ''),
            // Keputusan final selalu dari server; sini hanya menyalin.
            'decision' => ! empty($document->ai_verified) ? 'approved' : 'review',
            'reason' => (string) ($document->ai_reason ?? ''),
            'reason_code' => (string) ($document->ai_reason_code ?? ''),
            'blocking_issues' => (string) ($document->ai_blocking_issue ?? ''),
            'ocr_confidence' => isset($document->ai_score) ? (string) $document->ai_score : null,
            'observed_at' => isset($document->created_at) ? (string) $document->created_at : null,
            'source' => GoogleAppsScriptClient::SOURCE,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Log CBIR tidak usah dikirim ulang kalau CbirSearchLogger sudah menunjuk
     * Apps Script; modul ini hanya untuk backfill historis.
     */
    protected function cbirRecords(int $limit, ?string $since): Collection
    {
        if (! Schema::hasTable('cbir_search_logs')) {
            return new Collection;
        }

        $rows = DB::table('cbir_search_logs')
            ->select($this->existingColumns('cbir_search_logs', [
                'id', 'user_id', 'top_catalog_id', 'top_similarity', 'result_count',
                'duration_ms', 'created_at',
            ]))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($log) => array_filter([
            'search_id' => (string) $log->id,
            'user_id' => isset($log->user_id) ? (string) $log->user_id : null,
            'top_catalog_id' => isset($log->top_catalog_id) ? (string) $log->top_catalog_id : null,
            'top_similarity' => isset($log->top_similarity) ? (string) $log->top_similarity : null,
            'result_count' => isset($log->result_count) ? (string) $log->result_count : null,
            'duration_ms' => isset($log->duration_ms) ? (string) $log->duration_ms : null,
            'observed_at' => isset($log->created_at) ? (string) $log->created_at : null,
            'source' => GoogleAppsScriptClient::SOURCE,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Select kolom yang benar-benar ada: perintah ini harus tetap jalan pada
     * database yang belum dimigrasi penuh.
     *
     * @return array<int,string>
     */
    protected function existingColumns(string $table, array $candidates): array
    {
        return array_values(array_filter(
            $candidates,
            static fn (string $column) => Schema::hasColumn($table, $column),
        ));
    }
}
