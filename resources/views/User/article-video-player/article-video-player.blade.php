@php
    $videoUrl = $getRecord()->getMediaVideoUrlAttribute();
    // Gunakan konversi 'thumb' dari FFMPEG (via Spatie) jika ada, fallback ke image_url
    $posterUrl = $getRecord()->getFirstMediaUrl('videos', 'thumb') ?: $getRecord()->image_url;
@endphp

<div
    wire:ignore
    class="articles-video-player w-full bg-black rounded-2xl overflow-hidden shadow-lg mt-4"
>
    @if($videoUrl)
        <video 
            class="w-full aspect-video" 
            controls 
            playsinline
            preload="auto" 
            poster="{{ $posterUrl }}"
        >
            <source src="{{ $videoUrl }}" type="video/mp4">
            {{ __('Browser Anda tidak mendukung tag video.') }}
        </video>
    @else
        <div class="flex flex-col items-center justify-center p-12 bg-gray-100 rounded-2xl border-2 border-dashed border-gray-300">
            <x-heroicon-o-video-camera-slash class="w-12 h-12 text-gray-400 mb-2 opacity-50" />
            <p class="text-gray-500 italic">{{ __('Tidak ada video tersedia untuk artikel ini.') }}</p>
        </div>
    @endif
</div>