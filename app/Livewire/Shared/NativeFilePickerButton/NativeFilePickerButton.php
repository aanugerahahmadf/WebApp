<?php

namespace App\Livewire\Shared\NativeFilePickerButton;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Services\CBIRService\CBIRService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\File\File;

class NativeFilePickerButton extends Component
{
    use WithFileUploads;

    public bool $isLoading = false;

    public array $files = [];

    public array $recentUploads = [];

    public ?string $statusMessage = null;

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'mp4', 'mov', 'avi', 'mkv', 'pdf', 'docx', 'xlsx', 'txt', 'zip'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    private const FILE_ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,.jpg,.jpeg,.png,.webp,.heic,.mp4,.mov,.avi,.mkv,.pdf,.docx,.xlsx,.txt,.zip';

    public function openPicker(): void
    {
        $this->dispatch('native-file-picker-open-input', componentId: $this->getId());
    }

    public function updatedFiles(): void
    {
        if (empty($this->files)) {
            return;
        }

        $this->isLoading = true;
        $this->statusMessage = __('Mengunggah file...');
        $firstSearchableImage = null;

        foreach ($this->files as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $this->notifyUnsupportedFile();

                continue;
            }

            $path = $file->store('native-file-picker', 'public');
            $absolutePath = Storage::disk('public')->path($path);
            $mimeType = $file->getMimeType();

            $this->rememberUpload($path, $mimeType);

            if ($firstSearchableImage === null && in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $firstSearchableImage = $absolutePath;
            }
        }

        $this->files = [];

        if ($firstSearchableImage) {
            $this->runCbirSearch($firstSearchableImage);
        } elseif (! empty($this->recentUploads)) {
            $this->statusMessage = __('Multi upload selesai. File tersimpan di Laravel Storage.');
        }

        $this->isLoading = false;
    }

    private function runCbirSearch(string $absolutePath): void
    {
        if (! file_exists($absolutePath)) {
            return;
        }

        $this->statusMessage = __('Mencari dekorasi dari file gambar...');
        $cbirService = app(CBIRService::class);
        $response = $cbirService->searchByImage(new File($absolutePath), 20);

        if (isset($response['error']) || ! ($response['success'] ?? false)) {
            $this->statusMessage = $response['message'] ?? __('Upload selesai, tetapi pencarian gagal.');

            return;
        }

        $results = $response['results'] ?? [];

        if (! empty($results)) {
            $mixedResults = $this->buildCbirMixedResults($results);

            if (! empty($mixedResults)) {
                session()->put('cbir_mixed_results', $mixedResults);
                session()->put('cbir_package_results_ids', collect($mixedResults)->where('type', 'package')->pluck('data.id')->all());
                session()->put('cbir_search_time', $response['query_time_seconds'] ?? 0);
                session()->put('cbir_context', 'package');
                $this->statusMessage = __('Upload selesai. Hasil CBIR siap.');

                return;
            }
        }

        $this->statusMessage = __('Upload selesai. Tidak ada hasil CBIR yang cocok.');
    }

    private function rememberUpload(string $path, ?string $mimeType): void
    {
        array_unshift($this->recentUploads, [
            'name' => basename($path),
            'url' => Storage::disk('public')->url($path),
            'mime' => $mimeType ?: Storage::disk('public')->mimeType($path),
            'path' => $path,
        ]);

        $this->recentUploads = array_slice($this->recentUploads, 0, 6);
    }

    private function notifyUnsupportedFile(): void
    {
        $this->statusMessage = __('Ada file dengan format tidak didukung.');

        Notification::make()
            ->title(__('Format Tidak Didukung'))
            ->body(__('Gunakan Image, Video, PDF, DOCX, XLSX, TXT, atau ZIP.'))
            ->warning()
            ->send();
    }

    private function buildCbirMixedResults(array $results): array
    {
        $mixed = [];
        $seen = [];

        foreach ($results as $r) {
            $type = $r['type'] ?? 'package';
            $id = $r['owner_id'] ?? $r['id'] ?? null;

            if (! $id) {
                continue;
            }

            if (($r['similarity'] ?? 0) <= 0) {
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

    public function render()
    {
        return view('User.livewire.shared.native-file-picker-button.native-file-picker-button', [
            'fileAccept' => self::FILE_ACCEPT,
        ]);
    }
}
