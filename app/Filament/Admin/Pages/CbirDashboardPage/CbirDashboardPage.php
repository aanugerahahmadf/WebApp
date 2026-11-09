<?php

namespace App\Filament\Admin\Pages\CbirDashboardPage;

use App\Models\Cbir\CbirFeedback;
use App\Models\Cbir\CbirSearch;
use App\Models\Catalog\Catalog;
use App\Services\CBIRService\CBIRService;
use App\Support\AppIcon\AppIcon;
use App\Support\CbirSearchLogger\CbirSearchLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * "CBIR & Pencarian Visual" (`/admin/cbir`).
 *
 * Satu halaman untuk tiga hal yang sebelumnya tidak bisa dilihat dari mana pun:
 *
 *   1. kesehatan indeks (produk terindeks vs katalog di database, foto, jenis),
 *   2. angka evaluasi (MAP, MRR, first-rank, precision@3) yang dipakai di
 *      bab evaluasi skripsi,
 *   3. riwayat pencarian visual + umpan balik pengguna.
 *
 * Halaman ini TIDAK menulis indeks. Membangun ulang indeks butuh ±2 menit
 * (500 foto) dan `php artisan serve` hanya satu proses, jadi tombolnya sengaja
 * tidak ada -- perintahnya ditampilkan sebagai teks supaya bisa dijalankan di
 * terminal.
 */
class CbirDashboardPage extends Page
{
    protected static string $view = 'Admin.pages.cbir-dashboard.cbir-dashboard-page';

    protected static ?string $navigationIcon = AppIcon::FOLDER.'search.svg';

    protected static ?int $navigationSort = 2;

    /** Ringkasan `/status` AI Core (kosong = AI Core tidak menjawab). */
    public array $status = [];

    /** Hasil `/api/evaluate` (kosong = belum ada / AI Core mati). */
    public array $evaluation = [];

    /** Angka dari database Laravel. */
    public array $database = [];

    /** Riwayat pencarian terakhir. */
    public array $recentSearches = [];

    public function mount(): void
    {
        $this->loadStatus();
        $this->loadEvaluation();
        $this->loadDatabaseStats();
        $this->loadRecentSearches();
    }

    public function getTitle(): string
    {
        return __('CBIR & Pencarian Visual');
    }

    public static function getNavigationLabel(): string
    {
        return __('CBIR & Pencarian Visual');
    }

    public static function getSlug(): string
    {
        return 'cbir';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Beranda');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Baca ulang `/status` (tombol "Periksa Kembali").
     */
    public function refreshStatus(): void
    {
        $this->loadStatus();

        Notification::make()
            ->title($this->status === []
                ? __('AI Core tidak menjawab')
                : __('Status indeks diperbarui'))
            ->body($this->status === []
                ? __('Pastikan layanan AI Core berjalan di :url', ['url' => config('services.ai_core_url')])
                : __(':foto foto dari :produk produk terindeks', [
                    'foto' => number_format((int) $this->entryCount()),
                    'produk' => number_format((int) $this->itemCount()),
                ]))
            ->{$this->status === [] ? 'warning' : 'success'}()
            ->send();
    }

    /**
     * Hitung ulang evaluasi (`?refresh=1`).
     *
     * AI Core butuh ±35 detik untuk 500 foto, sedangkan batas PHP request web
     * hanya 30 detik -- tanpa `set_time_limit()` hasilnya HTTP 500. Nilai
     * batasnya dikembalikan di `finally` karena `set_time_limit()` berlaku
     * untuk seluruh proses.
     */
    public function recomputeEvaluation(): void
    {
        $previousLimit = (int) ini_get('max_execution_time');
        @set_time_limit(180);

        try {
            $payload = app(CBIRService::class)->getEvaluation(refresh: true, timeout: 170);

            if (! ($payload['success'] ?? false)) {
                Notification::make()
                    ->title(__('Evaluasi gagal dihitung'))
                    ->body($payload['message'] ?? __('AI Core tidak menjawab.'))
                    ->danger()
                    ->send();

                return;
            }

            $this->evaluation = $payload;
            $this->loadDatabaseStats();

            Notification::make()
                ->title(__('Evaluasi selesai dihitung ulang'))
                ->body(__(':queries kueri, MAP :map', [
                    'queries' => number_format((int) ($payload['n_queries'] ?? 0)),
                    'map' => number_format((float) ($payload['metrics']['map'] ?? 0), 4),
                ]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::error('CBIR evaluate dari panel admin gagal: '.$e->getMessage());

            Notification::make()
                ->title(__('Evaluasi gagal dihitung'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            @set_time_limit($previousLimit);
        }
    }

    private function loadStatus(): void
    {
        $status = app(CBIRService::class)->getStatus();

        $this->status = is_array($status) ? $status : [];
    }

    private function loadEvaluation(): void
    {
        // Tanpa `refresh`: AI Core menyimpan hasilnya 300 detik, jadi halaman
        // ini tidak pernah menahan request selama puluhan detik saat dibuka.
        $payload = app(CBIRService::class)->getEvaluation(refresh: false, timeout: 20);

        $this->evaluation = is_array($payload) ? $payload : [];
    }

    private function loadDatabaseStats(): void
    {
        $photos = Media::query()->where('collection_name', 'catalog_image')->count();

        $catalogs = Catalog::query()->count();

        $catalogsWithPhoto = Catalog::query()
            ->whereHas('media', fn ($query) => $query->where('collection_name', 'catalog_image'))
            ->count();

        $this->database = [
            'catalogs' => $catalogs,
            'catalogs_with_photo' => $catalogsWithPhoto,
            'catalog_photos' => $photos,
            'searches' => CbirSearch::query()->count(),
            'searches_last_7_days' => CbirSearch::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'average_top_similarity' => round((float) CbirSearch::query()->avg('top_similarity'), 2),
            'feedback' => CbirFeedback::query()->count(),
            'feedback_relevant' => CbirFeedback::query()->where('is_relevant', true)->count(),
        ];
    }

    private function loadRecentSearches(): void
    {
        $this->recentSearches = CbirSearch::query()
            ->with(['user', 'results'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (CbirSearch $search) => [
                'id' => $search->id,
                'user' => $search->user?->full_name ?? $search->user?->username ?? __('Pengguna #:id', ['id' => $search->user_id]),
                'query_image' => CbirSearchLogger::queryImageUrl($search->query_image_url),
                'results_count' => (int) $search->results_count,
                'top_similarity' => round((float) $search->top_similarity, 2),
                'top_item' => $this->topItemName($search),
                'created_at' => $search->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    /**
     * Nama produk dengan kemiripan tertinggi pada satu pencarian.
     */
    private function topItemName(CbirSearch $search): ?string
    {
        $result = $search->results->sortByDesc('similarity')->first();

        if (! $result) {
            return null;
        }

        $model = $result->type === 'package'
            ? \App\Models\Package\Package::find($result->item_id)
            : Catalog::find($result->item_id);

        return $model?->name;
    }

    public function entryCount(): int
    {
        return (int) ($this->status['total_entries'] ?? 0);
    }

    public function itemCount(): int
    {
        return (int) ($this->status['total_items'] ?? $this->status['total_catalogs'] ?? 0);
    }

    /**
     * Selisih produk terindeks dengan katalog yang punya foto.
     * `null` kalau AI Core tidak menjawab (tidak diketahui != 0).
     */
    public function indexGap(): ?int
    {
        if ($this->status === []) {
            return null;
        }

        return $this->itemCount() - (int) ($this->database['catalogs_with_photo'] ?? 0);
    }
}
