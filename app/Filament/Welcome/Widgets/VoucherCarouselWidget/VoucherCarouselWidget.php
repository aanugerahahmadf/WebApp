<?php

namespace App\Filament\Welcome\Widgets\VoucherCarouselWidget;

use App\Models\Voucher\Voucher;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class VoucherCarouselWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Voucher::where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
            )
            ->poll('60s')
            ->content(view('Welcome.components.voucher-carousel.voucher-carousel'))
            ->paginated(false);
    }
}
