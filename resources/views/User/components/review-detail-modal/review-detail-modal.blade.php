@php
    $review->loadMissing('user');
    $photos = $review->photo_urls ?? [];
@endphp

<div x-data="{ previewPhoto: null }" class="space-y-3 text-gray-900 dark:text-white">
    <div class="flex items-center gap-3">
        <img
            src="{{ $review->user?->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($review->user?->full_name ?? 'U') }}"
            alt="{{ $review->user?->full_name ?? __('Pengguna') }}"
            class="h-10 w-10 rounded-full object-cover"
        >
        <div>
            <p class="font-semibold">{{ $review->user?->full_name ?? __('Pengguna') }}</p>
            <span class="inline-flex items-center gap-1" role="img" aria-label="{{ __('Rating') }} {{ $review->rating }} dari 5">
                @for ($star = 1; $star <= 5; $star++)
                    <x-filament::icon icon="/images/icons/star.svg" @class(['h-5 w-5', 'text-amber-400' => $star <= (int) $review->rating, 'text-gray-300 dark:text-gray-600' => $star > (int) $review->rating]) />
                @endfor
            </span>
        </div>
    </div>

    @if (filled($review->title))
        <h3 class="text-lg font-semibold">{{ $review->title }}</h3>
    @endif

    <p class="whitespace-pre-line leading-relaxed">{{ $review->comment }}</p>

    @if (count($photos))
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach ($photos as $photo)
                <button
                    type="button"
                    @click.stop="previewPhoto = @js($photo)"
                    class="group block overflow-hidden rounded-lg ring-1 ring-gray-950/10 transition hover:ring-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:ring-white/10"
                    aria-label="{{ __('Lihat foto ulasan') }}"
                >
                    <img
                        src="{{ $photo }}"
                        alt="{{ __('Foto ulasan dari :name', ['name' => $review->user?->full_name ?? __('Pengguna')]) }}"
                        class="h-20 w-full cursor-zoom-in object-cover transition duration-200 group-hover:scale-105"
                    >
                </button>
            @endforeach
        </div>
    @endif

    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $review->created_at?->translatedFormat('d F Y') }}</p>

    {{-- Lightbox sederhana --}}
    <template x-teleport="body">
        <div
            x-show="previewPhoto"
            x-transition.opacity.duration.200ms
            @click="previewPhoto = null"
            @keydown.escape.window="previewPhoto = null"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            {{-- Close button --}}
            <button
                type="button"
                @click.stop="previewPhoto = null"
                class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/25"
                aria-label="{{ __('Tutup') }}"
            >
                <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
            </button>

            {{-- Image --}}
            <img
                x-show="previewPhoto"
                :src="previewPhoto"
                @click.stop
                alt="{{ __('Foto ulasan') }}"
                class="max-h-[90vh] max-w-full rounded-xl object-contain shadow-2xl"
            >
        </div>
    </template>
</div>
