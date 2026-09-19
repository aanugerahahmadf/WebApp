<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\EditOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return (string) ($this->record->order_number ?? '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => request()->query('from') === 'view'
                    ? OrderResource::getUrl('view', ['record' => $this->record])
                    : OrderResource::getUrl('index')),

            Actions\Action::make('view')
                ->label(__('Lihat Detail'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => OrderResource::getUrl('view', ['record' => $this->record])),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
