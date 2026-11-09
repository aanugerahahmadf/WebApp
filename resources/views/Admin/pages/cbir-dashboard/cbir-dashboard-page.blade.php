<x-filament-panels::page>
    @php
        $healthy = ($status['status'] ?? null) === 'healthy';
        $gap = $this->indexGap();
        $metrics = $evaluation['metrics'] ?? [];
        $percent = fn ($value) => number_format(((float) $value) * 100, 2).'%';
    @endphp

    {{-- ================= KESEHATAN INDEKS ================= --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Kesehatan Indeks CBIR') }}</x-slot>
        <x-slot name="description">
            {{ __('Jumlah FOTO dan jumlah PRODUK adalah dua angka berbeda: satu katalog punya lima foto galeri.') }}
        </x-slot>

        <div class="flex flex-wrap items-center gap-3">
            <span @class([
                'inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium',
                'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $healthy,
                'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' => ! $healthy,
            ])>
                <span @class([
                    'h-2.5 w-2.5 rounded-full',
                    'bg-success-500' => $healthy,
                    'bg-danger-500' => ! $healthy,
                ])></span>
                {{ $healthy ? __('AI Core aktif') : __('AI Core tidak menjawab') }}
            </span>

            @if (! empty($status['method']))
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ __('Metode') }}: {{ $status['method'] }}
                </span>
            @endif

            @if (! empty($status['metric']))
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ __('Metrik') }}: {{ $status['metric'] }}
                </span>
            @endif

            @if (! empty($status['version']))
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    v{{ $status['version'] }}
                </span>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ([
                ['label' => __('Produk terindeks'), 'value' => $this->itemCount(), 'hint' => __('katalog unik di indeks')],
                ['label' => __('Foto terindeks'), 'value' => $this->entryCount(), 'hint' => __('baris indeks (vektor fitur)')],
                ['label' => __('Katalog di database'), 'value' => $database['catalogs'] ?? 0, 'hint' => __('seluruh katalog')],
                ['label' => __('Foto katalog di database'), 'value' => $database['catalog_photos'] ?? 0, 'hint' => __('media catalog_image')],
                ['label' => __('Selisih indeks'), 'value' => $gap === null ? __('tidak diketahui') : ($gap > 0 ? '+'.$gap : (string) $gap), 'hint' => __('produk terindeks - katalog berfoto')],
            ] as $tile)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tile['hint'] }}</p>
                </div>
            @endforeach
        </div>

        @if (! empty($status['items_by_type']))
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Produk per jenis') }}:</span>
                @foreach ($status['items_by_type'] as $type => $count)
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                        {{ $type }}: {{ number_format((int) $count) }}
                    </span>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-filament::button wire:click="refreshStatus" icon="/images/icons/refresh.svg" color="gray">
                {{ __('Periksa Kembali') }}
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="url('/admin/catalogs')"
                icon="/images/icons/flower.svg"
                color="primary"
            >
                {{ __('Lihat Katalog Terindeks') }}
            </x-filament::button>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Membangun ulang indeks tidak dijalankan dari halaman ini (butuh +-2 menit untuk 500 foto dan hanya akan memblokir server).') }}
            <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">cd ai_core; python -m src.features.build_features</code>
            {{ __('lalu') }}
            <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">php artisan cbir:check</code>.
        </p>
    </x-filament::section>

    {{-- ================= EVALUASI ================= --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Evaluasi Kualitatif') }}</x-slot>
        <x-slot name="description">
            {{ __('MAP, MRR, first-rank accuracy, dan precision@3. Relevan = produk yang sama dengan foto kueri.') }}
        </x-slot>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                wire:click="recomputeEvaluation"
                wire:loading.attr="disabled"
                icon="/images/icons/refresh.svg"
                color="primary"
            >
                {{ __('Hitung Ulang') }}
            </x-filament::button>

            <span wire:loading wire:target="recomputeEvaluation" class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Menghitung +-35 detik untuk 500 foto...') }}
            </span>

            @if (! empty($evaluation['cached']))
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ __('memakai hasil tersimpan') }}
                </span>
            @endif

            @if (! empty($evaluation['generated_at']))
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Dihitung') }}: {{ $evaluation['generated_at'] }}
                </span>
            @endif
        </div>

        @if (empty($metrics))
            <div class="mt-4 rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('Belum ada angka evaluasi.') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Klik "Hitung Ulang" untuk menghitung dari indeks yang sekarang.') }}
                </p>
            </div>
        @else
            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ([
                    ['label' => 'MAP', 'value' => number_format((float) ($metrics['map'] ?? 0), 4)],
                    ['label' => 'MRR', 'value' => number_format((float) ($metrics['mrr'] ?? 0), 4)],
                    ['label' => __('First Rank Accuracy'), 'value' => $percent($metrics['first_rank_accuracy'] ?? 0)],
                    ['label' => 'Precision@3', 'value' => $percent($metrics['precision_at_3'] ?? 0)],
                ] as $tile)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $tile['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                <span>{{ __('Kueri') }}: {{ number_format((int) ($evaluation['n_queries'] ?? $metrics['n_queries'] ?? 0)) }}</span>
                @if (! empty($metrics['avg_query_time_s']))
                    <span>{{ __('Rata-rata waktu kueri') }}: {{ number_format((float) $metrics['avg_query_time_s'], 2) }} ms</span>
                @endif
                @if (! empty($metrics['method']))
                    <span>{{ __('Metode') }}: {{ $metrics['method'] }}</span>
                @endif
                @if (! empty($metrics['metric']))
                    <span>{{ __('Metrik') }}: {{ $metrics['metric'] }}</span>
                @endif
            </div>
        @endif

        <div class="mt-4 rounded-xl bg-warning-50 p-3 text-xs text-warning-800 dark:bg-warning-900/20 dark:text-warning-300">
            <p class="font-semibold">{{ __('Cara membaca angka ini') }}</p>
            <p class="mt-1">
                {{ __('Galeri contoh memakai 100 berkas foto yang dipakai ulang oleh 500 baris media (tiap berkas dipakai 5 katalog), dan 50 katalog belum punya kategori. Foto yang byte-identik memang boleh menjawab katalog lain, jadi first-rank accuracy di bawah 100% di sini adalah batas DATA, bukan algoritma.') }}
            </p>
        </div>
    </x-filament::section>

    {{-- ================= RIWAYAT PENCARIAN ================= --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Riwayat Pencarian Visual') }}</x-slot>
        <x-slot name="description">
            {{ __(':total pencarian tercatat, :week di antaranya dalam 7 hari terakhir, rata-rata kemiripan tertinggi :similarity persen.', [
                'total' => number_format((int) ($database['searches'] ?? 0)),
                'week' => number_format((int) ($database['searches_last_7_days'] ?? 0)),
                'similarity' => number_format((float) ($database['average_top_similarity'] ?? 0), 2),
            ]) }}
        </x-slot>

        @if (empty($recentSearches))
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('Belum ada pencarian visual yang tercatat.') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Coba buka halaman Pencarian Visual (ikon kamera di bilah pencarian), lalu cari memakai foto. Riwayatnya muncul di sini.') }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">{{ __('Foto Kueri') }}</th>
                            <th class="px-3 py-2">{{ __('Pengguna') }}</th>
                            <th class="px-3 py-2">{{ __('Hasil') }}</th>
                            <th class="px-3 py-2">{{ __('Kemiripan Tertinggi') }}</th>
                            <th class="px-3 py-2">{{ __('Produk Teratas') }}</th>
                            <th class="px-3 py-2">{{ __('Waktu') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recentSearches as $search)
                            <tr>
                                <td class="px-3 py-2">
                                    @if ($search['query_image'])
                                        <img src="{{ $search['query_image'] }}" alt="{{ __('Foto kueri') }}"
                                             class="h-12 w-12 rounded-lg object-cover" />
                                    @else
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400 dark:bg-gray-800">
                                            {{ __('tidak ada') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $search['user'] }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $search['results_count'] }}</td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $search['top_similarity'] >= 80,
                                        'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300' => $search['top_similarity'] < 80,
                                    ])>
                                        {{ number_format((float) $search['top_similarity'], 2) }}%
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $search['top_item'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $search['created_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Umpan balik relevan: :relevant dari :total', [
                'relevant' => number_format((int) ($database['feedback_relevant'] ?? 0)),
                'total' => number_format((int) ($database['feedback'] ?? 0)),
            ]) }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
