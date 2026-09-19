<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\ListOrders;

use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Exports\OrderExporter\OrderExporter;
use App\Filament\Admin\Resources\OrderResource\OrderResource;
use App\Models\Order\Order;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(OrderExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Order'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Order Ditambahkan'))
                        ->body(__('Order baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('Semua'))
                ->badge(fn () => Order::count()),
            'pending' => Tab::make(OrderStatus::PENDING->getLabel())
                ->icon(OrderStatus::PENDING->getIcon())
                ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::PENDING))
                ->badge(fn () => Order::where('status', OrderStatus::PENDING)->count())
                ->badgeColor(OrderStatus::PENDING->getColor()),
            'confirmed' => Tab::make(OrderStatus::CONFIRMED->getLabel())
                ->icon(OrderStatus::CONFIRMED->getIcon())
                ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::CONFIRMED))
                ->badge(fn () => Order::where('status', OrderStatus::CONFIRMED)->count())
                ->badgeColor(OrderStatus::CONFIRMED->getColor()),
            'completed' => Tab::make(OrderStatus::COMPLETED->getLabel())
                ->icon(OrderStatus::COMPLETED->getIcon())
                ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::COMPLETED))
                ->badge(fn () => Order::where('status', OrderStatus::COMPLETED)->count())
                ->badgeColor(OrderStatus::COMPLETED->getColor()),
            'cancelled' => Tab::make(OrderStatus::CANCELLED->getLabel())
                ->icon(OrderStatus::CANCELLED->getIcon())
                ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::CANCELLED))
                ->badge(fn () => Order::where('status', OrderStatus::CANCELLED)->count())
                ->badgeColor(OrderStatus::CANCELLED->getColor()),
        ];
    }
}
