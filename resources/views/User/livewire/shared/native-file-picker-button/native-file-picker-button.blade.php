<div
    class="relative inline-flex items-center justify-center w-7 h-7"
    x-data
>
    <input
        x-ref="filePicker"
        type="file"
        class="sr-only"
        accept="{{ $fileAccept }}"
        multiple
        wire:model.live="files"
    >

    @if($isLoading)
        <svg class="animate-spin w-[18px] h-[18px] text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @else
        <button
            type="button"
            id="cbir-filepicker-btn"
            x-on:click="$refs.filePicker.click()"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            wire:target="files"
            title="{{ __('Upload File') }}"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md
                   text-gray-400 hover:text-primary-500 dark:hover:text-primary-400
                   transition-all duration-150 active:scale-90 touch-manipulation
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
        </button>
    @endif

    @if($statusMessage || ! empty($recentUploads))
        <div class="absolute right-0 top-8 z-40 w-64 rounded-lg bg-white p-2 text-xs shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
            @if($statusMessage)
                <div class="mb-2 text-gray-600 dark:text-gray-300">{{ $statusMessage }}</div>
            @endif
            <div class="grid grid-cols-3 gap-1">
                @foreach($recentUploads as $upload)
                    @if(str_starts_with($upload['mime'] ?? '', 'image/'))
                        <img src="{{ $upload['url'] }}" alt="{{ $upload['name'] }}" class="h-12 w-full rounded object-cover">
                    @elseif(str_starts_with($upload['mime'] ?? '', 'video/'))
                        <video src="{{ $upload['url'] }}" class="h-12 w-full rounded object-cover" muted controls></video>
                    @else
                        <a href="{{ $upload['url'] }}" target="_blank" class="flex h-12 items-center justify-center rounded bg-gray-100 px-1 text-[10px] font-medium uppercase text-gray-600 dark:bg-white/10 dark:text-gray-200">
                            {{ pathinfo($upload['name'], PATHINFO_EXTENSION) }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
