@php($banners = $this->getBanners())

<div>
    @if ($banners->isNotEmpty())
        <section aria-label="{{ __('Banner promosi') }}" class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ __('Promo pilihan untuk Anda') }}
            </h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Penawaran terbaru') }}
            </span>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($banners as $banner)
                @php($href = filled($banner->link_url) ? $banner->link_url : null)
                @if ($href)
                    <a href="{{ $href }}" class="group block overflow-hidden rounded-2xl bg-gray-100 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md dark:bg-gray-800 dark:ring-white/10">
                @else
                    <article class="group block overflow-hidden rounded-2xl bg-gray-100 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                @endif
                    <div class="relative aspect-[16/5] overflow-hidden bg-gray-200 dark:bg-gray-700">
                        <img
                            src="{{ $banner->publicImageUrl() }}"
                            alt="{{ $banner->title }}"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                        >
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-4 pb-4 pt-10 text-white">
                            <h3 class="font-semibold">{{ $banner->title }}</h3>
                            @if (filled($banner->subtitle))
                                <p class="mt-1 line-clamp-2 text-sm text-white/85">{{ $banner->subtitle }}</p>
                            @endif
                        </div>
                    </div>
                @if ($href)
                    </a>
                @else
                    </article>
                @endif
            @endforeach
        </div>
        </section>
    @endif
</div>
