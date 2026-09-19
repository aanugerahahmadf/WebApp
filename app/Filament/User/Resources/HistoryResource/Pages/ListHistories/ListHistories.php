<?php

namespace App\Filament\User\Resources\HistoryResource\Pages\ListHistories;

use App\Filament\User\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\User\Resources\HistoryResource\HistoryResource;
use App\Models\History\History;
use Filament\Facades\Filament;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListHistories extends ListRecords
{
    use HasMobilePagination;

    protected static string $resource = HistoryResource::class;

    // public function getTabs(): array
    // {
    //     return [
    //         'all' => Tab::make(__('Semua'))
    //             ->badge(fn () => History::where('user_id', Filament::auth()->id())->count()),
    //         'order' => Tab::make(__('Pembelian'))
    //             ->modifyQueryUsing(fn ($query) => $query->where('type', 'order'))
    //             ->badge(fn () => History::where('user_id', Filament::auth()->id())->where('type', 'order')->count())
    //             ->badgeColor('info'),
    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
