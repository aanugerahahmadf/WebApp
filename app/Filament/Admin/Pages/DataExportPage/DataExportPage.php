<?php

namespace App\Filament\Admin\Pages\DataExportPage;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Services\DataExportService\DataExportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Component;

class DataExportPage extends Page implements HasForms
{
    use HasDynamicBreadcrumbs;
    use InteractsWithForms;

    protected static string $view = 'Admin.pages.data-export-page.data-export-page';

    protected static ?string $navigationIcon = 'heroicon-s-arrow-down-tray';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Pusat Unduhan');
    }

    public static function getSlug(): string
    {
        return 'data-export';
    }

    public function getTitle(): string
    {
        return __('Pusat Unduhan Data');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }

    public function mount(): void
    {
        $this->form->fill(
            collect(DataExportService::datasets())->mapWithKeys(fn (array $dataset, string $key): array => [$key => true])->all()
        );
    }

    public function form(Form $form): Form
    {
        $counts = DataExportService::counts();

        return $form
            ->schema([
                Forms\Components\Section::make(__('Unduh Semua Data Aplikasi'))
                    ->description(__('Aktif/nonaktifkan toggle per dataset, lalu klik Unduh Data dan pilih format file (Excel atau PDF) pada modal yang muncul.'))
                    ->icon('heroicon-s-arrow-down-tray')
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema(
                                collect(DataExportService::datasets())
                                    ->map(fn (array $dataset, string $key): Forms\Components\Toggle => Forms\Components\Toggle::make($key)
                                        ->label($dataset['label'])
                                        ->helperText(__('Total :count baris', ['count' => number_format($counts[$key] ?? 0)]))
                                        ->inline(false)
                                        ->default(true))
                                    ->values()
                                    ->all()
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh_data')
                ->label(__('Unduh Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button()
                ->modalHeading(__('Pilih Format Unduhan'))
                ->modalDescription(__('Dataset yang aktif akan digabung ke dalam satu file.'))
                ->modalSubmitActionLabel(__('Unduh'))
                ->modalCancelActionLabel(__('Batal'))
                ->form([
                    Forms\Components\Radio::make('format')
                        ->label(__('Format File'))
                        ->options([
                            'xlsx' => __('Excel / Spreadsheet (.xlsx) — satu sheet per dataset'),
                            'pdf' => __('PDF (.pdf) — satu bagian per dataset'),
                        ])
                        ->descriptions([
                            'xlsx' => __('Cocok untuk olah data lanjutan.'),
                            'pdf' => __('Cocok untuk arsip / cetak.'),
                        ])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data, Component $livewire): void {
                    $format = $data['format'] ?? 'xlsx';
                    if (! in_array($format, ['xlsx', 'pdf'], true)) {
                        $format = 'xlsx';
                    }

                    $keys = collect($this->form->getState())->filter()->keys()->values()->all();

                    if ($keys === []) {
                        Notification::make()
                            ->title(__('Pilih minimal satu dataset.'))
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $filePath = $format === 'pdf'
                            ? DataExportService::buildPdf($keys, auth()->user())
                            : DataExportService::buildWorkbook($keys, auth()->user());
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title(__('Gagal membuat arsip.'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    // Unduhan lewat GET biasa (bukan POST Livewire) sehingga
                    // tidak ada risiko 419 Page Expired.
                    $livewire->redirect(
                        route('admin.data-exports.download', ['file' => basename($filePath)]),
                        navigate: false
                    );
                }),
        ];
    }
}
