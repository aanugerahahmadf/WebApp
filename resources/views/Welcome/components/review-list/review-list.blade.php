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
                        <form method="POST" action="{{ route('welcome.reviews.report', $review) }}" @click.stop class="shrink-0">
                            @csrf
                            <x-filament::button type="submit" color="danger" outlined size="xs" icon="heroicon-m-flag">{{ __('Lapor') }}</x-filament::button>
                        </form>
                    </div>
                    <div class="mt-0.5 flex items-center gap-1" role="img" aria-label="{{ __('Rating') }} {{ (int) $review->rating }} dari 5">
                        @for ($star = 1; $star <= 5; $star++)
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="16" height="16" class="h-4 w-4 {{ $star <= (int) $review->rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600' }}"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                        @endfor
                    </div>
                    @if (filled($review->title))
                        <p class="mt-1 break-words text-sm font-bold">{{ $review->title }}</p>
                    @endif
                    @if ($hasComment)
                        <p class="mt-0.5 whitespace-pre-line break-words text-sm">{{ $review->comment }}</p>
                    @endif
                    @if ($hasMedia)
                        <div class="mt-2 flex min-w-0 flex-row flex-wrap gap-2">
                            @foreach ($review->photo_urls as $photo)
                                <img src="{{ $photo }}" alt="{{ __('Foto ulasan') }}" class="h-16 w-16 shrink-0 rounded-lg object-cover" loading="lazy">
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
