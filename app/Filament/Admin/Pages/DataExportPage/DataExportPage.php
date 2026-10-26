<?php

namespace App\Filament\Admin\Pages\DataExportPage;

use App\Services\DataExportService\DataExportService;
use App\Support\AppIcon\AppIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Pusat Unduhan Data.
 *
 * Admin centang dataset yang mau diunduh, lalu pilih formatnya. Berkasnya
 * dibuat di server (`DataExportService`) dan DIUNDUH lewat iframe tersembunyi
 * -- bukan `redirect()`.
 *
 * Kenapa bukan redirect: redirect = navigasi penuh, jadi browser sempat
 * memuat ulang dokumen. Begitu token/sesi bergeser (mis. karena login di
 * tab lain), livewire.js memunculkan dialog "This page has expired" tepat
 * setelah unduhan selesai -- padahal yang terjadi cuma unduhan.
 */
class DataExportPage extends Page
{

    protected static string $view = 'Admin.pages.data-export-page.data-export-page';

    protected static ?string $navigationIcon = AppIcon::EXPORT;

    /**
     * Key dataset yang dicentang.
     *
     * @var array<string, bool>
     */
    public array $data = [];

    /**
     * URL berkas yang baru dibuat (dipakai iframe tersembunyi).
     */
    public ?string $downloadUrl = null;

    public function getTitle(): string
    {
        return __('Pusat Unduhan Data');
    }

    /**
     * Slug halaman: `data-export`, bukan `data-export-page`.
     *
     * Filament menurunkan slug dari nama class, jadi tanpa override ini
     * URL-nya jadi `/admin/data-export-page`.
     */
    public static function getSlug(): string
    {
        return 'data-export';
    }

    public static function getNavigationLabel(): string
    {
        return __('Pusat Unduhan Data');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        $counts = DataExportService::counts();

        $options = [];

        foreach (DataExportService::groups() as $group) {
            foreach (DataExportService::datasets() as $key => $dataset) {
                if (($dataset['group'] ?? null) !== $group) {
                    continue;
                }

                $options[$dataset['label'].' ('.($counts[$key] ?? 0).')'] = $key;
            }
        }

        return $form
            ->schema([
                CheckboxList::make('data')
                    ->label(__('Pilih Data yang Ingin Diunduh'))
                    ->options($options)
                    ->columns(3)
                    ->bulkToggleable()
                    ->searchable()
                    ->helperText(__('Bisa pilih beberapa sekaligus. Arsip berisi semua tabel yang dipilih.')),
            ])
            ->statePath('data');
    }

    /**
     * Aksi unduh: buat arsip, lalu trigger unduhan lewat iframe.
     */
    public function unduhDataAction(): Action
    {
        return Action::make('unduh_data')
            ->label(__('Unduh Data'))
            ->icon('/images/icons/export.svg')
            ->color('primary')
            ->form([
                Select::make('format')
                    ->searchable()
                    ->label(__('Format Berkas'))
                    ->options([
                        'xlsx' => __('Excel (.xlsx)'),
                        'pdf' => __('PDF (.pdf)'),
                    ])
                    ->default('xlsx')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $keys = array_keys(array_filter($this->data));

                if ($keys === []) {
                    Notification::make()
                        ->title(__('Pilih data yang ingin diunduh dulu.'))
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $actor = $this->actor();

                    $path = ($data['format'] ?? 'xlsx') === 'pdf'
                        ? DataExportService::buildPdf($keys, $actor)
                        : DataExportService::buildWorkbook($keys, $actor);
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title(__('Gagal membuat arsip.'))
                        ->danger()
                        ->send();

                    return;
                }

                $this->downloadUrl = route('admin.data-exports.download', ['file' => basename($path)]);

                $this->dispatch('unduh_data', url: $this->downloadUrl);

                Notification::make()
                    ->title(__('Arsip siap diunduh.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Siapa yang menjemduh.
     *
     * Panel admin memakai guard `admin`, jadi actor-nya model `Admin` --
     * bukan `User`. Relasi `exports.user` yang polimorfik mencatatnya apa
     * adanya.
     */
    protected function actor(): Authenticatable
    {
        $actor = Auth::guard('admin')->user();

        if ($actor instanceof Authenticatable) {
            return $actor;
        }

        return Auth::user();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->unduhDataAction(),
        ];
    }
}
