<?php

namespace App\Filament\Admin\Pages\DataExportPage;

use App\Filament\Admin\Pages\Home\Home;
use App\Services\DataExportService\DataExportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Component;

class DataExportPage extends Page
{
    protected static string $view = 'Admin.pages.data-export-page.data-export-page';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?int $navigationSort = 99;

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
            Home::getUrl() => __('Beranda'),
            $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh_data')
                ->label(__('Unduh Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button()
                ->modalHeading(__('Pilih Data yang Diunduh'))
                ->modalDescription(__('Aktifkan toggle untuk dataset yang ingin digabung ke dalam satu file ZIP (satu XLSX per dataset).'))
                ->modalSubmitActionLabel(__('Unduh ZIP'))
                ->modalWidth('lg')
                ->form(fn (): array => $this->datasetToggles())
                ->action(function (array $data, Component $livewire): void {
                    $keys = collect($data)->filter()->keys()->values()->all();

                    if ($keys === []) {
                        Notification::make()
                            ->title(__('Pilih minimal satu dataset.'))
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $zipPath = DataExportService::buildZip($keys, auth()->user());
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title(__('Gagal membuat arsip.'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $livewire->redirect(
                        route('admin.data-exports.download', ['file' => basename($zipPath)]),
                        navigate: false
                    );
                }),
        ];
    }

    /**
     * @return array<string, Forms\Components\Toggle>
     */
    protected function datasetToggles(): array
    {
        $counts = DataExportService::counts();

        return collect(DataExportService::datasets())
            ->mapWithKeys(fn (array $dataset, string $key) => [
                $key => Forms\Components\Toggle::make($key)
                    ->label($dataset['label'])
                    ->helperText(__('Total :count baris', ['count' => number_format($counts[$key] ?? 0)]))
                    ->default(true),
            ])
            ->all();
    }
}
