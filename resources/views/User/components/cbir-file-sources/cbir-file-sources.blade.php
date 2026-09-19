@php
    use App\Support\PlatformContext\PlatformContext;

    // Menghitung daftar sumber file berdasarkan platform (native app vs web browser).
    // Dipakai bersama oleh modal gabungan kamera (topbar) & cbir-browse-modal.
    $isNative = $isNative ?? false;
    $platformSlug = PlatformContext::current()->value;

    if ($isNative) {
        // NativePHP Mobile (Android / iOS)
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
        // Web browser (Windows / macOS / Android browser / iOS browser)
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