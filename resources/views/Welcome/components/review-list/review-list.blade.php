@php
    $list = isset($reviews) ? collect($reviews) : collect();
@endphp
<div
    x-data="{ reviewFilter: '{{ $activeFilter ?? 'all' }}' }"
    @review-filter.window="reviewFilter = $event.detail"
    class="flex w-full min-w-0 flex-col gap-2"
>
    @forelse ($list as $review)
        @php
            $hasComment = filled($review->comment);
            $hasMedia = filled($review->photo_urls);
            $avatar = $review->user?->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($review->user?->full_name ?? 'U');
        @endphp
        <article
            x-data="{ openReview: false }"
            x-show="reviewFilter === 'all' || reviewFilter === 'rating_{{ (int) $review->rating }}' || (reviewFilter === 'comment' && {{ $hasComment ? 'true' : 'false' }}) || (reviewFilter === 'media' && {{ $hasMedia ? 'true' : 'false' }})"
            @click="openReview = true"
            @keydown.enter="openReview = true"
            role="button"
            tabindex="0"
            class="relative w-full min-w-0 cursor-pointer rounded-xl bg-white p-3 text-gray-950 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-white dark:ring-white/10 sm:p-4"
        >
            <div class="flex min-w-0 items-start gap-2.5">
                <img
                    src="{{ $avatar }}"
                    alt="{{ $review->user?->full_name ?? __('Pengguna') }}"
                    class="h-10 w-10 shrink-0 rounded-full object-cover"
                    loading="lazy"
                >
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $review->user?->full_name ?? __('Pengguna') }}</p>
                        <form method="POST" action="{{ route('reviews.report', $review) }}" @click.stop class="shrink-0">
                            @csrf
                            <x-filament::button type="submit" color="danger" outlined size="xs" icon="/images/icons/flag.svg">{{ __('Lapor') }}</x-filament::button>
                        </form>
                    </div>
                    <div class="mt-0.5 flex items-center gap-1" role="img" aria-label="{{ __('Rating') }} {{ (int) $review->rating }} dari 5">
                        @for ($star = 1; $star <= 5; $star++)
                            <x-filament::icon icon="/images/icons/star.svg" class="h-4 w-4 {{ $star <= (int) $review->rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600' }}" />
                        @endfor
                    </div>
                    @if (filled($review->title))
                        <p class="mt-1 break-words text-sm font-bold">{{ $review->title }}</p>
                    @endif
                    @if ($hasComment)
                        <p class="mt-0.5 whitespace-pre-line break-words text-sm">{{ $review->comment }}</p>
                    @endif
                    @if ($hasMedia)
                        <div class="mt-2 flex min-w-0 flex-row flex-wrap gap-1.5">
                            @foreach ($review->photo_urls as $photo)
                                <button type="button" @click.stop="openReview = true" class="block">
                                    <img src="{{ $photo }}" alt="{{ __('Foto ulasan') }}" class="h-16 w-16 shrink-0 rounded-lg object-cover cursor-pointer" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at?->format('d M Y H:i') }}</p>
                    <div class="mt-1.5">
                        @include('Welcome.components.review-vote-button.review-vote-button', ['review' => $review])
                    </div>
                </div>
            </div>
            @include('Welcome.components.review-card-modal.review-card-modal', ['review' => $review])
        </article>
    @empty
        <p class="rounded-xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">{{ __('Tidak ada ulasan untuk filter ini.') }}</p>
    @endforelse
</div>
