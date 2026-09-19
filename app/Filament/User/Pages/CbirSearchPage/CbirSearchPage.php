<?php

namespace App\Filament\User\Pages\CbirSearchPage;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use App\Services\CBIRService\CBIRService;
use App\Support\PlatformContext\PlatformContext;
use emmanpbarrameda\FilamentTakePictureField\Forms\Components\TakePicture;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Native\Mobile\Events\Camera\PermissionDenied;
use Native\Mobile\Events\Camera\PhotoCancelled;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Camera\VideoCancelled;
use Native\Mobile\Events\Camera\VideoRecorded;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;
use Native\Mobile\Facades\File as NativeFile;
use Symfony\Component\HttpFoundation\File\File;

class CbirSearchPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    private const CAMERA_ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv';

    protected static string $view = 'User.pages.cbir-search.cbir-search';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'cbir-search';
    }

    public ?string $mode = null;

    public ?array $data = [];

    public ?string $statusMessage = null;

    public bool $isProcessing = false;

    public ?TemporaryUploadedFile $cameraUpload = null;

    public ?TemporaryUploadedFile $browseUpload = null;

    public function mount(): void
    {
        $this->mode = request()->query('mode', 'camera');
        $this->form->fill();
    }

    /**
     * Open native camera / gallery on NativePHP apps (Android/iOS)
     */
    public function openCamera(string $mode = 'photo-back'): void
    {
        if (! NativeServiceProvider::isNativeMobile()) {
            return;
        }

        $this->isProcessing = true;
        $this->statusMessage = __('Membuka kamera...');

        match ($mode) {
            'video' => Camera::recordVideo(['maxDuration' => 120])->id('cbir-search-video')->start(),
            'gallery' => Camera::pickImages('all', false)->id('cbir-search-gallery')->start(),
            default => Camera::getPhoto(['camera' => $mode === 'photo-front' ? 'front' : 'rear'])
                ->id('cbir-search-photo')
                ->start(),
        };
    }

    public function updatedCameraUpload(): void
    {
        if (! $this->cameraUpload) {
            return;
        }

        $this->isProcessing = true;
        $this->processUploadedFile($this->cameraUpload);
        $this->cameraUpload = null;
        $this->isProcessing = false;
    }

    public function updatedBrowseUpload(): void
    {
        if (! $this->browseUpload) {
            return;
        }

        $this->isProcessing = true;
        $this->processUploadedFile($this->browseUpload);
        $this->browseUpload = null;
        $this->isProcessing = false;
    }

    /**
     * Open native gallery / file picker (Android/iOS).
     *
     * @param  'image'|'video'|'all'  $mediaType
     */
    public function openBrowseSource(string $mediaType = 'all', ?string $sourceId = null): void
    {
        if (! NativeServiceProvider::isNativeMobile()) {
            return;
        }

        $this->isProcessing = true;
        $this->statusMessage = __('Membuka pemilih file...');

        $mediaType = in_array($mediaType, ['image', 'video', 'all'], true) ? $mediaType : 'all';

        Camera::pickImages($mediaType, false)
            ->id('cbir-browse-'.($sourceId ?? $mediaType))
            ->start();
    }

    /**
     * Listen for PhotoTaken event from NativePHP native camera
     */
    #[On('native:'.PhotoTaken::class)]
    public function onPhotoTaken(string $path, string $mimeType = 'image/jpeg'): void
    {
        $this->isProcessing = false;
        $this->processNativeFile($path, $mimeType);
    }

    #[On('native:'.VideoRecorded::class)]
    public function onVideoRecorded(string $path, string $mimeType = 'video/mp4', ?string $id = null): void
    {
        $this->isProcessing = false;
        $this->processNativeFile($path, $mimeType);
    }

    #[On('native:'.MediaSelected::class)]
    public function onMediaSelected(bool $success, array $files = [], int $count = 0, ?string $error = null, bool $cancelled = false): void
    {
        $this->isProcessing = false;

        if ($cancelled || ! $success || empty($files)) {
            return;
        }

        $file = $files[0];
        $path = is_string($file) ? $file : ($file['path'] ?? null);
        $mimeType = is_array($file) ? ($file['mimeType'] ?? $file['mime_type'] ?? null) : null;

        if ($path) {
            $this->processNativeFile($path, $mimeType);
        }
    }

    #[On('native:'.PhotoCancelled::class)]
    #[On('native:'.VideoCancelled::class)]
    public function onCaptureCancelled(?string $id = null): void
    {
        $this->isProcessing = false;
        $this->statusMessage = null;
    }

    #[On('native:'.PermissionDenied::class)]
    public function onPermissionDenied(string $action, ?string $id = null): void
    {
        $this->isProcessing = false;

        Notification::make()
            ->title(__('Izin Kamera Diperlukan'))
            ->body(__('Harap aktifkan izin kamera di Pengaturan > Izin Aplikasi.'))
            ->warning()
            ->send();
    }

    public function form(Form $form): Form
    {
        $cameraMode = PlatformContext::cbirCameraMode();
        $isNative = $cameraMode === 'native';

        $cameraFields = [
            Forms\Components\View::make('User.components.cbir-camera-options.cbir-camera-options')
                // Output only: input kamera/galeri dipindah ke global search.
                ->visible(false)
                ->viewData(fn () => [
                    'isNative' => $isNative,
                    'isProcessing' => $this->isProcessing,
                    'cameraAccept' => self::CAMERA_ACCEPT,
                ]),
        ];

        if (! $isNative) {
            // Web browser: hidden TakePicture WebRTC — triggered via menu (cbir-open-webrtc-camera event)
            $cameraFields[] = TakePicture::make('camera_image')
                ->hiddenLabel()
                ->visible(false)
                ->live()
                ->disk('public')
                ->directory('cbir-camera')
                ->extraAttributes(['class' => 'cbir-take-picture-hidden'])
                ->registerActions([
                    Forms\Components\Actions\Action::make('manualSearch')
                        ->label(__('Cari Sekarang'))
                        ->icon('heroicon-m-arrow-up-tray')
                        ->color('primary')
                        ->action(function ($state, Forms\Set $set, CBIRService $cbirService) {
                            if (! $state) {
                                return;
                            }

                            $this->clearVisualSearch();
                            $this->statusMessage = __('Mengunggah & Mencari...');
                            $set('status_message', $this->statusMessage);

                            $filePath = $this->resolveTakePicturePath($state);

                            if (! $filePath || ! file_exists($filePath)) {
                                $this->statusMessage = __('Gagal memproses gambar.');

                                return;
                            }

                            $this->runCbirSearch(new File($filePath), $cbirService);
                            $set('status_message', $this->statusMessage);
                        }),
                ])
                ->afterStateUpdated(function ($state, Forms\Set $set, CBIRService $cbirService) {
                    if (! $state) {
                        return;
                    }

                    if (str_starts_with($state, 'data:image/')) {
                        $filePath = $this->resolveTakePicturePath($state);
                        if ($filePath && file_exists($filePath)) {
                            $this->runCbirSearch(new File($filePath), $cbirService);
                            $set('status_message', $this->statusMessage);
                        }

                        return;
                    }

                    $filePath = storage_path('app/public/'.$state);
                    if (file_exists($filePath)) {
                        $this->runCbirSearch(new File($filePath), $cbirService);
                        $set('status_message', $this->statusMessage);
                    }
                });
        }

        return $form
            ->schema([
                Forms\Components\View::make('User.components.cbir-browse-options.cbir-browse-options')
                    // Output only: input kamera/galeri dipindah ke global search.
                    ->visible(false)
                    ->viewData(fn () => [
                        'isNative' => $isNative,
                        'browseAccept' => self::CAMERA_ACCEPT,
                    ]),

                Forms\Components\Placeholder::make('status_message')
                    ->label('')
                    ->content(fn (Forms\Get $get) => new HtmlString(
                        '<div class="text-sm text-center">'.e($get('status_message') ?? $this->statusMessage ?? '').'</div>'
                    ))
                    ->visible(fn (Forms\Get $get) => (bool) ($get('status_message') ?? $this->statusMessage))
                    ->extraAttributes(['class' => 'p-3 bg-gray-900/80 dark:bg-gray-800 rounded-xl text-white font-medium shadow-md']),

                Forms\Components\View::make('User.components.cbir-results-preview.cbir-results-preview')
                    ->visible(fn () => ! empty(session('cbir_mixed_results'))),

                Forms\Components\Placeholder::make('cbir_no_match')
                    ->label('')
                    ->content(fn () => new HtmlString(
                        '<div class="flex flex-col items-center gap-3 py-14 text-center">
                            <svg class="h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">'.e(__('Tidak ada dekorasi yang cocok dengan pencarian Anda.')).'</p>
                            <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">'.e(__('Coba dengan foto yang lebih jelas atau cari dekorasi lain.')).'</p>
                        </div>'
                    ))
                    ->visible(fn () => (bool) session('cbir_no_match')),

                Forms\Components\Placeholder::make('cbir_empty_state')
                    ->label('')
                    ->content(fn () => new HtmlString(
                        '<div class="flex flex-col items-center gap-3 py-14 text-center">
                            <svg class="h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0M18.75 10.5h.008v.008h-.008V10.5Z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">'.e(__('Belum ada hasil pencarian visual.')).'</p>
                            <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">'.e(__('Gunakan ikon kamera atau galeri di bilah pencarian global untuk mencari dekorasi berdasarkan foto. Hasil dari kamera dan galeri otomatis digabungkan di halaman ini.')).'</p>
                        </div>'
                    ))
                    ->visible(fn (Forms\Get $get) => ! ($get('status_message') ?? $this->statusMessage) && empty(session('cbir_mixed_results')) && ! session('cbir_no_match')),
            ])
            ->statePath('data');
    }

    private function processUploadedFile(TemporaryUploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! $this->isAllowedExtension($extension)) {
            $this->notifyUnsupportedFile();

            return;
        }

        $path = $file->store('cbir-camera', 'public');
        $absolutePath = Storage::disk('public')->path($path);

        if ($this->isImageExtension($extension)) {
            $this->statusMessage = __('Memproses foto...');
            $this->runCbirSearch(new File($absolutePath), app(CBIRService::class));
        } else {
            $this->statusMessage = __('File berhasil diunggah. Hanya gambar yang dapat digunakan untuk pencarian visual.');
        }
    }

    private function processNativeFile(string $path, ?string $mimeType): void
    {
        if (! file_exists($path)) {
            $this->statusMessage = __('Gagal membaca file. Silakan coba lagi.');

            return;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! $this->isAllowedExtension($extension)) {
            $this->notifyUnsupportedFile();

            return;
        }

        $storedPath = 'cbir-camera/'.uniqid('native-', true).'.'.$extension;
        $destination = Storage::disk('public')->path($storedPath);

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        $copied = NativeServiceProvider::isNativeMobile()
            ? NativeFile::copy($path, $destination)
            : copy($path, $destination);

        if (! $copied) {
            $this->statusMessage = __('Gagal menyimpan file.');

            return;
        }

        if ($this->isImageExtension($extension)) {
            $this->statusMessage = __('Memproses foto...');
            $this->runCbirSearch(new File($destination), app(CBIRService::class));
        } else {
            $this->statusMessage = __('Video berhasil diunggah.');
        }
    }

    private function resolveTakePicturePath(string $state): ?string
    {
        if (str_starts_with($state, 'data:image/')) {
            $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $state);
            $filename = 'cbir-temp-'.time().'.jpg';
            $dir = 'cbir-camera';
            if (! is_dir(storage_path('app/public/'.$dir))) {
                mkdir(storage_path('app/public/'.$dir), 0755, true);
            }
            $filePath = storage_path('app/public/'.$dir.'/'.$filename);
            file_put_contents($filePath, base64_decode($base64Data));

            return $filePath;
        }

        return storage_path('app/public/'.$state);
    }

    private function isAllowedExtension(string $extension): bool
    {
        return $this->isImageExtension($extension) || in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    private function isImageExtension(string $extension): bool
    {
        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    private function notifyUnsupportedFile(): void
    {
        $this->statusMessage = __('Format file tidak didukung.');

        Notification::make()
            ->title(__('Format Tidak Didukung'))
            ->body(__('Gunakan JPG, JPEG, PNG, WEBP, HEIC, MP4, MOV, AVI, atau MKV.'))
            ->warning()
            ->send();
    }

    private function runCbirSearch(File $file, CBIRService $cbirService): void
    {
        $this->statusMessage = __('Mencari dekorasi...');
        Log::info('CBIR Search: Starting search for file: '.$file->getFilename());

        $response = $cbirService->searchByImage($file, 20);

        if (isset($response['error']) || ! ($response['success'] ?? false)) {
            $this->statusMessage = $response['message'] ?? __('Server AI Offline.');
            Log::error('CBIR Search Error:', ['message' => $this->statusMessage]);

            return;
        }

        $results = $response['results'] ?? [];
        Log::info('CBIR Search raw results from AI Core:', ['count' => count($results), 'sample' => array_slice($results, 0, 2)]);

        if (! empty($results)) {
            $mixedResults = $this->buildCbirMixedResults($results);
            Log::info('CBIR Search mixed results after mapping:', ['count' => count($mixedResults)]);

            if (empty($mixedResults)) {
                $this->statusMessage = __('Hasil ditemukan oleh AI, tapi tidak ada di database kita.');
                Log::warning('CBIR Search: Results from AI Core did not match any database records.');
session()->forget(['cbir_mixed_results', 'cbir_package_results_ids', 'cbir_search_time', 'cbir_context', 'cbir_no_match']);

                return;
            }

            session()->put('cbir_mixed_results', $mixedResults);
            session()->put('cbir_package_results_ids', collect($mixedResults)->where('type', 'package')->pluck('data.id')->all());
            session()->put('cbir_search_time', $response['query_time_seconds'] ?? 0);
            session()->put('cbir_context', 'package');

            $topScore = number_format(($mixedResults[0]['similarity'] ?? 0), 1);
            $this->statusMessage = __('Berhasil menemukan :count hasil!', [
                'count' => count($mixedResults),
            ]);
        } else {
            session()->forget(['cbir_mixed_results', 'cbir_package_results_ids', 'cbir_search_time', 'cbir_context']);
            $this->statusMessage = __('Tidak ada dekorasi yang cocok.');
            Log::info('CBIR Search: No matching decorations found by AI Core.');
        }
    }

    private function buildCbirMixedResults(array $results): array
    {
        $mixed = [];
        $seen = []; // Tracking seen items to prevent duplicates

        foreach ($results as $r) {
            $type = $r['type'] ?? 'package';
            $id = $r['owner_id'] ?? $r['id'] ?? null;

            if (! $id) {
                continue;
            }

            // Skip results with 0% similarity (non-matches)
            if (($r['similarity'] ?? 0) <= 0) {
                continue;
            }

            // Create a unique key for this item type and ID
            $key = "{$type}_{$id}";

            // If we've already seen this item with a higher or equal similarity, skip it
            if (isset($seen[$key])) {
                continue;
            }

            $model = $type === 'package'
                ? Package::with(['category'])->find($id)
                : Product::with(['category'])->find($id);

            if (! $model) {
                continue;
            }

            $mixed[] = [
                'type' => $type,
                'similarity' => $r['similarity'] ?? (($r['score'] ?? 0) * 100),
                'data' => array_merge($model->toArray(), [
                    'image_url' => $model->image_url,
                    'category' => $model->category?->toArray(),
                    'rating' => number_format($model->reviews()->avg('rating') ?: 0, 1),
                    'stock' => $model->stock ?? 0,
                ]),
            ];

            $seen[$key] = true;
        }
        usort($mixed, fn ($a, $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));

        return $mixed;
    }

    public function clearVisualSearch(): void
    {
        session()->forget(['cbir_mixed_results', 'cbir_package_results_ids', 'cbir_search_time', 'cbir_context']);
        $this->statusMessage = null;
    }

    public function searchFromCameraPhoto(?string $photoData = null): void
    {
        if (! $photoData) {
            return;
        }

        $filePath = $this->resolveTakePicturePath($photoData);

        if ($filePath && file_exists($filePath)) {
            $this->runCbirSearch(new File($filePath), app(CBIRService::class));
        }
    }

    public function getTitle(): string
    {
        return __('Pencarian Visual');
    }
}
