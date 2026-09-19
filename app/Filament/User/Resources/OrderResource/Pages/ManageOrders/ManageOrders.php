<?php

namespace App\Filament\User\Resources\OrderResource\Pages\ManageOrders;

use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\User\Resources\OrderResource\OrderResource;
use App\Models\Order\Order;
use Filament\Facades\Filament;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;

class ManageOrders extends ManageRecords
{
    use HasMobilePagination;

    protected static string $resource = OrderResource::class;

    // public function getTabs(): array
    // {
    //     return [
    //         'all' => Tab::make(__('Semua'))
    //             ->badge(fn () => Order::where('user_id', Filament::auth()->id())->count()),
    //         'pending' => Tab::make(OrderStatus::PENDING->getLabel())
    //             ->icon(OrderStatus::PENDING->getIcon())
    //             ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::PENDING))
    //             ->badge(fn () => Order::where('user_id', Filament::auth()->id())->where('status', OrderStatus::PENDING)->count())
    //             ->badgeColor(OrderStatus::PENDING->getColor()),
    //         'confirmed' => Tab::make(OrderStatus::CONFIRMED->getLabel())
    //             ->icon(OrderStatus::CONFIRMED->getIcon())
    //             ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::CONFIRMED))
    //             ->badge(fn () => Order::where('user_id', Filament::auth()->id())->where('status', OrderStatus::CONFIRMED)->count())
    //             ->badgeColor(OrderStatus::CONFIRMED->getColor()),
    //         'completed' => Tab::make(OrderStatus::COMPLETED->getLabel())
    //             ->icon(OrderStatus::COMPLETED->getIcon())
    //             ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::COMPLETED))
    //             ->badge(fn () => Order::where('user_id', Filament::auth()->id())->where('status', OrderStatus::COMPLETED)->count())
    //             ->badgeColor(OrderStatus::COMPLETED->getColor()),
    //         'cancelled' => Tab::make(OrderStatus::CANCELLED->getLabel())
    //             ->icon(OrderStatus::CANCELLED->getIcon())
    //             ->modifyQueryUsing(fn ($query) => $query->where('status', OrderStatus::CANCELLED))
    //             ->badge(fn () => Order::where('user_id', Filament::auth()->id())->where('status', OrderStatus::CANCELLED)->count())
    //             ->badgeColor(OrderStatus::CANCELLED->getColor()),
    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [
            // No creation action for user
        ];
    }
}
