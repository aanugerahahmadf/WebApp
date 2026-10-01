<?php

namespace App\Livewire\Welcome\CbirCameraButton;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Services\CBIRService\CBIRService;
use emmanpbarrameda\FilamentTakePictureField\Forms\Components\TakePicture;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The camera icon that sits in the topbar, next to the global search field.
 *
 * Lives in the `Welcome` namespace but is mounted from *two* panel topbars:
 * `UserPanelProvider` (via the `filament-panels` global-search field override in
 * resources/views/User/vendor/...) and `WelcomePanelProvider`, which resolves the
 * very same override file. One class, two entry points.
 *
 * Platform notes: this used to branch on NativePHP (`Native\Mobile\Facades\Camera`
 * and its PhotoTaken / VideoRecorded / MediaSelected events) and therefore only
 * did anything inside the native shell. NativePHP is gone -- the shells are
 * Capacitor now, and Capacitor's WebView is a real browser -- so every surface
 * goes through the WebRTC `TakePicture` viewfinder plus plain `<input type="file">`
 * elements, both of which the vendor take-picture view already whitelists for
 * `capacitor://` and `localhost`. AppPlatform is still consulted, but only for
 * the *labels* of the "pick from gallery / files" sheet, not for behaviour.
 *
 * Not called "Native..." any more: nothing in here is native-only.
 */
class CbirCameraButton extends Component implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    public bool $isLoading = false;

    /**
     * Fed by the hidden `<input type="file">` elements rendered by
     * `Welcome.components.cbir-camera-options` (galeri / file / video capture).
     */
    public ?TemporaryUploadedFile $cameraUpload = null;

    /** Fed by the hidden "pick any file" input in the same partial. */
    public ?TemporaryUploadedFile $browseUpload = null;

    public ?array $data = [];

    public array $recentUploads = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        // Inisialisasi state form agar entangle `data.camera_image` di blade
        // selalu menemukan propertinya (tanpa ini: "Livewire Entangle Error").
        $this->form->fill();
    }

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv'];

    private const CAMERA_ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv';

    /**
     * Where the results are shown. The search page still only exists on the user
     * panel -- `App\Filament\Welcome\Pages\CbirSearchPage` was never created, so
     * there is an orphan blade at resources/views/Welcome/pages/cbir-search/.
     *
     * Consequence: a guest who takes a photo from the public storefront lands on
     * the user panel's login. Deliberate for now; the alternative is duplicating
     * the page into the Welcome panel.
     */
    private const RESULTS_ROUTE = 'filament.welcome.pages.cbir-search';

    public function clearVisualSearch(): void
    {
        session()->forget(['cbir_mixed_results', 'cbir_package_results_ids', 'cbir_search_time', 'cbir_context', 'cbir_no_match']);
        $this->statusMessage = null;
    }

    public function updatedCameraUpload(): void
    {
        if (! $this->cameraUpload) {
            return;
        }

        $this->isLoading = true;
        $this->storeAndProcessUploadedFile($this->cameraUpload);
        $this->cameraUpload = null;
        $this->isLoading = false;
    }

    public function updatedBrowseUpload(): void
    {
        if (! $this->browseUpload) {
            return;
        }

        $this->isLoading = true;
        $this->storeAndProcessUploadedFile($this->browseUpload);
        $this->browseUpload = null;
        $this->isLoading = false;
    }

    /**
     * Called by the take-picture modal's "Pakai Foto" button. `photoData` is either
     * a `data:image/...` blob (canvas capture) or a storage path.
     *
     * @see searchFromCameraPhoto() in resources/views/Welcome/vendor/filament-take-picture-field
     */
    public function searchFromCameraPhoto(?string $photoData = null): void
    {
        if (! $photoData) {
            return;
        }

        $filePath = $this->resolveTakePicturePath($photoData);

        if ($filePath && file_exists($filePath)) {
            $this->runCbirSearch($filePath);
        }
    }

    /**
     * The WebRTC viewfinder itself.
     *
     * Kept hidden and zero-sized: it is opened by dispatching the
     * `cbir-open-webrtc-camera` window event that the vendor take-picture view
     * listens for, so this method only has to carry the form state. The facing
     * mode and capture mode are handled client-side.
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            TakePicture::make('camera_image')
                ->hiddenLabel()
                ->live()
                ->disk('public')
                ->directory('cbir-camera')
                // Nothing is searched here on purpose: the modal's confirm button
                // calls searchFromCameraPhoto(), and a Livewire redirect is only
                // allowed from an action, not from a form state update.
                ->extraAttributes(['class' => 'cbir-take-picture-hidden']),
        ])->statePath('data');
    }

    private function storeAndProcessUploadedFile(TemporaryUploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! $this->isAllowedExtension($extension)) {
            $this->notifyUnsupportedFile();

            return;
        }

        $path = $file->store('cbir-camera', 'public');
        $absolutePath = Storage::disk('public')->path($path);
        $this->rememberUpload($path, $file->getMimeType());

        if ($this->isImageExtension($extension)) {
            $this->runCbirSearch($absolutePath);

            return;
        }

        // Video is stored but not searchable -- CBIR indexes stills.
        $this->statusMessage = __('Video berhasil diunggah ke Storage.');
    }

    private function resolveTakePicturePath(string $state): ?string
    {
        if (str_starts_with($state, 'data:image/')) {
            $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $state);
            $directory = 'cbir-camera';

            if (! is_dir(storage_path('app/public/'.$directory))) {
                mkdir(storage_path('app/public/'.$directory), 0755, true);
            }

            $filePath = storage_path('app/public/'.$directory.'/cbir-temp-'.time().'.jpg');
            file_put_contents($filePath, base64_decode($base64Data));

            return $filePath;
        }

        return storage_path('app/public/'.$state);
    }

    private function runCbirSearch(string $absolutePath): void
    {
        if (! file_exists($absolutePath)) {
            return;
        }

        $this->statusMessage = __('Mencari dekorasi...');
        $response = app(CBIRService::class)->searchByImage(new File($absolutePath), 20);

        if (isset($response['error']) || ! ($response['success'] ?? false)) {
            $this->statusMessage = $response['message'] ?? __('File berhasil diunggah, tetapi pencarian gagal.');

            return;
        }

        $mixedResults = $this->buildCbirMixedResults($response['results'] ?? []);

        session()->put('cbir_mixed_results', $mixedResults);
        session()->put('cbir_package_results_ids', collect($mixedResults)->where('type', 'package')->pluck('data.id')->all());
        session()->put('cbir_search_time', $response['query_time_seconds'] ?? 0);
        session()->put('cbir_context', 'package');

        // Honest about an empty index rather than showing a fake result set.
        session()->put('cbir_no_match', empty($mixedResults));

        // Open the results page either way, so the "nothing matched" state has
        // somewhere to live.
        $this->redirectRoute(self::RESULTS_ROUTE);
    }

    private function buildCbirMixedResults(array $results): array
    {
        $mixed = [];
        $seen = [];

        foreach ($results as $result) {
            $type = $result['type'] ?? 'package';
            $id = $result['owner_id'] ?? $result['id'] ?? null;

            if (! $id || ($result['similarity'] ?? 0) <= 0) {
                continue;
            }

            $key = "{$type}_{$id}";

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
                'similarity' => $result['similarity'] ?? (($result['score'] ?? 0) * 100),
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

    private function rememberUpload(string $path, ?string $mimeType): void
    {
        array_unshift($this->recentUploads, [
            'name' => basename($path),
            'url' => Storage::disk('public')->url($path),
            'mime' => $mimeType ?: Storage::disk('public')->mimeType($path),
            'path' => $path,
        ]);

        $this->recentUploads = array_slice($this->recentUploads, 0, 3);
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

    public function render()
    {
        return view('Welcome.livewire.cbir-camera-button.cbir-camera-button', [
            'cameraAccept' => self::CAMERA_ACCEPT,
            // Always false now: there is no native camera bridge to dispatch to,
            // so every surface uses the WebRTC viewfinder. Still passed as a
            // variable because cbir-camera-options is also fed by the user
            // panel's CbirSearchPage.
            'isNative' => false,
        ]);
    }
}
