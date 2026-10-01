@php
    $browseAccept = $browseAccept ?? 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv,.webm';
    $isNative = $isNative ?? false;
    // compact: untuk topbar/global search — hanya render input tersembunyi
    // (dipakai cbir-browse-modal.pickWeb), tanpa kotak drop-zone besar.
    $compact = $compact ?? false;
@endphp

<div
    class="cbir-browse-root relative {{ $compact ? '' : 'py-2' }}"
    x-data="{
        browseAccept: @js($browseAccept),
        openPicker() {
            window.dispatchEvent(new CustomEvent('cbir-browse-open'));
        },
        handleDrop(event) {
            const file = event.dataTransfer?.files?.[0];
            if (! file || ! this.$wire) return;
            this.$wire.upload('browseUpload', file);
        }
    }"
>
    @unless ($isNative)
        <input
            id="cbir-browse-file-input"
            type="file"
            class="sr-only"
            accept="{{ $browseAccept }}"
            x-on:change="
                const file = $event.target.files?.[0];
                if (file && $wire) {
                    $wire.upload('browseUpload', file);
                }
                $event.target.value = '';
            "
        >
    @endunless

    @unless ($compact)
    <div
        class="relative rounded-lg border-2 border-dashed border-gray-300 bg-white px-6 py-8 text-center shadow-sm transition-colors hover:border-primary-400 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500"
        x-on:dragover.prevent
        x-on:drop.prevent="handleDrop($event)"
        wire:loading.class="pointer-events-none opacity-60"
        wire:target="browseUpload, openBrowseSource"
    >
        <div
            wire:loading.flex
            wire:target="browseUpload, openBrowseSource"
            class="absolute inset-0 z-10 hidden items-center justify-center rounded-lg bg-white/80 dark:bg-gray-900/80"
        >
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <svg class="h-5 w-5 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('Memproses...') }}</span>
            </div>
        </div>

        {{-- Match Filament FileUpload (FilePond) label style exactly --}}
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {!! __('Seret & Jatuhkan berkas Anda atau') !!}
            <span
                role="button"
                tabindex="0"
                class="text-primary-600 underline underline-offset-2 hover:text-primary-500 dark:text-primary-400 cursor-pointer"
                x-on:click.stop="openPicker()"
                x-on:keydown.enter.stop="openPicker()"
            >{{ __('Jelajahi') }}</span>
        </p>
    </div>
    @endunless
</div>
