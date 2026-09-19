@php
    $cameraAccept = $cameraAccept ?? 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv';
    $isNative = $isNative ?? false;
@endphp

{{-- Hidden file inputs. Bekerja di browser maupun WebView Android/iOS:
     - Galeri: input image -> membuka galeri asli
     - File  : input */*  -> membuka file/document picker asli
     - Video : input video capture -> membuka kamera video (WebView) --}}
<div
    class="sr-only"
    x-data="{}"
    x-on:cbir-pick-gallery.window="$refs.gallery.click()"
    x-on:cbir-pick-file.window="$refs.file.click()"
    x-on:cbir-pick-video.window="$refs.video.click()"
>
    <input x-ref="gallery" type="file" accept="{{ $cameraAccept }}" wire:model.live="cameraUpload">
    <input x-ref="file" type="file" accept="*/*" wire:model.live="browseUpload">
    <input x-ref="video" type="file" accept="video/*" capture="environment" wire:model.live="cameraUpload">
</div>

{{-- Modal 5 tombol: Belakang, Depan, Video, Galeri, File --}}
<div
    x-data="{ open: false }"
    class="py-2"
    x-on:cbir-open-camera.window="open = true"
>
    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity
            x-on:keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/60 p-0 sm:items-center sm:p-4"
            x-on:click.self="open = false"
        >
            <div
                x-show="open"
                x-transition
                x-on:click.stop
                class="w-full max-w-xl rounded-t-2xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:rounded-2xl"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Ambil Foto') }}</h3>
                    <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-3">
                    @php
                        $options = [
                            [
                                'label' => __('Foto Kamera Belakang'),
                                'icon' => 'heroicon-o-camera',
                                'click' => $isNative
                                    ? "window.dispatchEvent(new CustomEvent('cbir-open-camera')); \$wire.openCamera('photo-back')"
                                    : "open = false; window.dispatchEvent(new CustomEvent('cbir-open-webrtc-camera', { detail: { facing: 'environment' } }))",
                            ],
                            [
                                'label' => __('Foto Kamera Depan'),
                                'icon' => 'heroicon-o-user-circle',
                                'click' => $isNative
                                    ? "window.dispatchEvent(new CustomEvent('cbir-open-camera')); \$wire.openCamera('photo-front')"
                                    : "open = false; window.dispatchEvent(new CustomEvent('cbir-open-webrtc-camera', { detail: { facing: 'user' } }))",
                            ],
                            [
                                'label' => __('Rekam Video'),
                                'icon' => 'heroicon-o-video-camera',
                                'click' => $isNative
                                    ? "window.dispatchEvent(new CustomEvent('cbir-open-camera')); \$wire.openCamera('video')"
                                    : "open = false; window.dispatchEvent(new CustomEvent('cbir-pick-video'))",
                            ],
                            [
                                'label' => __('Pilih dari Galeri'),
                                'icon' => 'heroicon-o-photo',
                                'click' => "open = false; window.dispatchEvent(new CustomEvent('cbir-pick-gallery'))",
                            ],
                            [
                                'label' => __('Pilih dari File'),
                                'icon' => 'heroicon-o-folder',
                                'click' => "open = false; window.dispatchEvent(new CustomEvent('cbir-pick-file'))",
                            ],
                        ];
                    @endphp
                    @foreach ($options as $opt)
                        <button
                            type="button"
                            x-on:click="{{ $opt['click'] }}"
                            class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center transition-all hover:border-primary-400 hover:bg-primary-50 active:scale-95 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                        >
                            <x-filament::icon :icon="$opt['icon']" class="h-6 w-6 text-primary-500" />
                            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ $opt['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>