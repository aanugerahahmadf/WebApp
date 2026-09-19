<div class="relative flex items-center gap-0.5">
    @if($isLoading)
        <svg class="animate-spin w-[18px] h-[18px] text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @else
        {{-- Tombol kamera tunggal → langsung buka KAMERA di modal (viewfinder), tanpa daftar opsi --}}
        <button
            type="button"
            id="cbir-camera-btn"
            @if($isNative)
                x-on:click="window.dispatchEvent(new CustomEvent('cbir-open-camera'))"
            @else
                x-on:click="window.dispatchEvent(new CustomEvent('cbir-open-webrtc-camera', { detail: { facing: 'environment' } }))"
            @endif
            title="{{ __('Pencarian Visual') }}"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md
                   text-gray-400 hover:text-primary-500 dark:hover:text-primary-400
                   transition-all duration-150 active:scale-90 touch-manipulation
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
        </button>
    @endif

    {{-- TakePicture WebRTC: membuka viewfinder kamera di modal, dengan bar mode
         (Belakang / Depan / Video / Galeri). 'Galeri' → cbir-pick-gallery. --}}
    <div class="cbir-take-picture-hidden w-0 h-0" aria-hidden="true">
        {{ $this->form }}
    </div>

    {{-- Menangani galeri/video dari dalam modal kamera (WebRTC). --}}
    @include('User.components.cbir-camera-options.cbir-camera-options', [
        'isNative' => $isNative,
        'cameraAccept' => $cameraAccept,
        'sources' => $sources,
    ])
</div>