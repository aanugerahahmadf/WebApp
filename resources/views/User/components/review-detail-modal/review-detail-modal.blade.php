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
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20" @class(['h-5 w-5', 'text-amber-400' => $star <= (int) $review->rating, 'text-gray-300 dark:text-gray-600' => $star > (int) $review->rating])><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
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
                        class="h-24 w-full cursor-zoom-in object-cover transition duration-200 group-hover:scale-105"
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
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
