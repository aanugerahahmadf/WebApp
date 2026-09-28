@php
    $filters = [
        'all' => __('Semua'),
        'rating_5' => 5,
        'rating_4' => 4,
        'rating_3' => 3,
        'rating_2' => 2,
        'rating_1' => 1,
        'comment' => __('Dengan Komentar'),
        'media' => __('Dengan Media'),
    ];
@endphp

<section id="reviews" x-data="{ reviewFilter: '{{ $activeFilter }}' }" @review-filter.window="reviewFilter = $event.detail" class="space-y-4">
    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $heading }}</h2>

    <div class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-[180px_1fr] md:items-center">
        <div class="flex flex-col items-center justify-center gap-1 text-center">
            <div class="text-3xl font-semibold text-primary-600 dark:text-primary-400">
                {{ number_format($average, 1) }} <span class="text-base font-normal">{{ __('dari 5') }}</span>
            </div>
            <div class="flex items-center gap-1" aria-label="{{ __('Rating rata-rata') }}">
                @for ($star = 1; $star <= 5; $star++)
                    <x-filament::icon icon="heroicon-s-star" @class(['h-5 w-5', 'text-amber-400' => $star <= round($average), 'text-gray-300 dark:text-gray-600' => $star > round($average)]) />
                @endfor
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ trans_choice(':count penilaian|:count penilaian', $total, ['count' => $total]) }}</span>
        </div>

        <nav aria-label="{{ __('Filter penilaian') }}" class="flex flex-wrap gap-2">
            @foreach ($filters as $filter => $label)
                @php
                    $isRating = str_starts_with($filter, 'rating_');
                    $count = match ($filter) {
                        'all' => $total,
                        'comment' => $commentCount,
                        'media' => $mediaCount,
                        default => (int) ($ratingCounts[(int) substr($filter, 7)] ?? 0),
                    };
                @endphp
                <button
                    type="button"
                    @click="$dispatch('review-filter', '{{ $filter }}')"
                    :class="reviewFilter === '{{ $filter }}' ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'border-gray-300 bg-white text-gray-700 hover:border-primary-400 dark:border-white/15 dark:bg-white/5 dark:text-gray-200'"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm transition"
                >
                    @if ($isRating)
                        @for ($star = 0; $star < (int) $label; $star++)
                            <x-filament::icon icon="heroicon-s-star" class="h-4 w-4 text-amber-400" />
                        @endfor
                    @else
                        {{ $label }}
                    @endif
                    <span>({{ number_format($count) }})</span>
                </button>
            @endforeach
        </nav>
    </div>
</section>
