@php
    use App\Support\PlatformContext\PlatformContext;

    $isNative = $isNative ?? false;
    $browseAccept = $browseAccept ?? 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv,.webm';

    $platformSlug = PlatformContext::current()->value;

    if ($isNative) {
        $isAndroid = str_contains($platformSlug, 'android');
        $isIos     = str_contains($platformSlug, 'ios');

        $sources = [
            [
                'id'    => 'internal',
                'icon'  => 'heroicon-o-folder',
                'label' => $isAndroid ? __('Buka Files Android') : __('Buka Files iOS'),
                'pick'  => 'all',
            ],
            [
                'id'    => 'photos',
                'icon'  => 'heroicon-o-photo',
                'label' => __('Buka Album Foto'),
                'pick'  => 'image',
            ],
            [
                'id'    => 'videos',
                'icon'  => 'heroicon-o-video-camera',
                'label' => __('Buka Galeri Video'),
                'pick'  => 'video',
            ],
            [
                'id'    => 'cloud',
                'icon'  => 'heroicon-o-cloud',
                'label' => $isAndroid ? __('Buka Google Drive') : __('Buka iCloud Drive'),
                'pick'  => 'all',
            ],
        ];
    } else {
        $isWindows = str_contains($platformSlug, 'windows');
        $isMac     = str_contains($platformSlug, 'macos');
        $isAndroid = str_contains($platformSlug, 'android');
        $isIos     = str_contains($platformSlug, 'ios');

        $fileManagerLabel = match (true) {
            $isWindows => __('Buka File Explorer Windows'),
            $isMac     => __('Buka Finder macOS'),
            $isAndroid => __('Buka Files Android'),
            $isIos     => __('Buka Files iOS'),
            default    => __('Pilih File'),
        };

        $cloudLabel = match (true) {
            $isWindows, $isAndroid => __('Buka Google Drive'),
            $isMac, $isIos         => __('Buka iCloud Drive'),
            default                => __('Buka Google Drive / iCloud'),
        };

        $sources = [
            [
                'id'    => 'file-manager',
                'icon'  => 'heroicon-o-folder',
                'label' => $fileManagerLabel,
                'pick'  => 'all',
            ],
            [
                'id'    => 'photos',
                'icon'  => 'heroicon-o-photo',
                'label' => __('Buka Album Foto'),
                'pick'  => 'image',
            ],
            [
                'id'    => 'videos',
                'icon'  => 'heroicon-o-video-camera',
                'label' => __('Buka Galeri Video'),
                'pick'  => 'video',
            ],
            [
                'id'    => 'cloud',
                'icon'  => 'heroicon-o-cloud',
                'label' => $cloudLabel,
                'pick'  => 'all',
            ],
        ];
    }
@endphp

<style data-navigate-track>
    .cbir-browse-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 0.75rem;
        padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
        background-color: rgb(3 7 18 / 0.78);
    }

    @media (min-width: 640px) {
        .cbir-browse-modal-backdrop {
            align-items: center;
            padding: 1rem;
        }
    }

    .cbir-browse-modal-panel {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 28rem;
        max-height: min(78dvh, calc(100dvh - 1.5rem - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)));
        border-radius: 1rem;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(255 255 255);
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.35);
    }

    .dark .cbir-browse-modal-panel {
        border-color: rgb(255 255 255 / 0.1);
        background-color: rgb(17 24 39);
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.6);
    }

    .cbir-browse-modal-header {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgb(229 231 235);
        padding: 0.75rem 1rem;
    }

    .dark .cbir-browse-modal-header {
        border-bottom-color: rgb(255 255 255 / 0.1);
    }

    .cbir-browse-modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 0.75rem 1rem 1rem;
        -webkit-overflow-scrolling: touch;
    }

    .cbir-browse-modal-hint {
        margin-bottom: 0.75rem;
        font-size: 0.75rem;
        line-height: 1.5;
        color: rgb(107 114 128);
    }

    .dark .cbir-browse-modal-hint {
        color: rgb(156 163 175);
    }

    .cbir-browse-modal-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .cbir-browse-modal-option {
        display: flex;
        width: 100%;
        align-items: center;
        gap: 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        padding: 0.625rem 0.75rem;
        text-align: left;
        transition: border-color 0.15s, background-color 0.15s;
    }

    .cbir-browse-modal-option:active {
        transform: scale(0.99);
    }

    .cbir-browse-modal-option:hover {
        border-color: rgb(245 158 11);
        background-color: rgb(255 251 235);
    }

    .dark .cbir-browse-modal-option {
        border-color: rgb(255 255 255 / 0.1);
        background-color: rgb(255 255 255 / 0.05);
    }

    .dark .cbir-browse-modal-option:hover {
        border-color: rgb(245 158 11);
        background-color: rgb(245 158 11 / 0.1);
    }

    .cbir-browse-modal-option-icon {
        display: flex;
        height: 2.25rem;
        width: 2.25rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background-color: rgb(245 158 11 / 0.1);
    }

    .cbir-browse-modal-option-label {
        min-width: 0;
        flex: 1;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgb(17 24 39);
    }

    .dark .cbir-browse-modal-option-label {
        color: rgb(255 255 255);
    }

    body.cbir-browse-modal-open .fi-bottom-nav {
        visibility: hidden;
        pointer-events: none;
    }
</style>

<div
    id="cbir-browse-modal-root"
    x-data="{
        open: false,
        browseAccept: @js($browseAccept),
        isNative: @js($isNative),
        openModal() {
            this.open = true;
        },
        closeModal() {
            this.open = false;
        },
        pickWeb(kind) {
            const input = document.getElementById('cbir-browse-file-input');
            if (! input) return;

            input.removeAttribute('capture');
            input.accept = kind === 'image'
                ? 'image/*'
                : kind === 'video'
                    ? 'video/*'
                    : this.browseAccept;
            input.value = '';
            this.closeModal();

            requestAnimationFrame(() => input.click());
        }
    }"
    x-init="
        document.body.classList.remove('overflow-hidden', 'cbir-browse-modal-open');
        $watch('open', value => {
            document.body.classList.toggle('overflow-hidden', value);
            document.body.classList.toggle('cbir-browse-modal-open', value);
        });
    "
    x-on:cbir-browse-open.window="openModal()"
    x-on:keydown.escape.window="if (open) closeModal()"
    x-on:livewire:navigating.window="closeModal()"
>
    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-50"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-50"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="closeModal()"
            class="cbir-browse-modal-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cbir-browse-title"
            style="display: none;"
        >
            <div
                x-show="open"
                x-on:click.stop
                x-transition:enter="transition ease-out duration-50"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="cbir-browse-modal-panel"
            >
                <div class="cbir-browse-modal-header">
                    <h3 id="cbir-browse-title" class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('Pilih Sumber File') }}
                    </h3>
                    <button
                        type="button"
                        x-on:click="closeModal()"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        aria-label="{{ __('Tutup') }}"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="cbir-browse-modal-body">
                    <p class="cbir-browse-modal-hint">
                        @if ($isNative)
                            {{ __('Pilih sumber file di perangkat Anda.') }}
                        @else
                            {{ __('File picker sistem akan terbuka. Google Drive / iCloud tersedia jika sudah terpasang di perangkat Anda.') }}
                        @endif
                    </p>

                    <div class="cbir-browse-modal-list">
                        @foreach ($sources as $source)
                            <button
                                type="button"
                                @if ($isNative)
                                    wire:click="openBrowseSource('{{ $source['pick'] }}', '{{ $source['id'] }}')"
                                    x-on:click="closeModal()"
                                @else
                                    x-on:click="pickWeb('{{ $source['pick'] }}')"
                                @endif
                                class="cbir-browse-modal-option"
                            >
                                <span class="cbir-browse-modal-option-icon">
                                    <x-filament::icon :icon="$source['icon']" class="h-5 w-5 text-primary-500" />
                                </span>
                                <span class="cbir-browse-modal-option-label">{{ $source['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
