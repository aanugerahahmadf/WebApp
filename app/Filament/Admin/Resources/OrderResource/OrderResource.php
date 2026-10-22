<?php

namespace App\Filament\Admin\Resources\OrderResource;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder\CreateOrder;
use App\Filament\Admin\Resources\OrderResource\Pages\EditOrder\EditOrder;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders\ListOrders;
use App\Filament\Admin\Resources\OrderResource\Pages\ViewOrder\ViewOrder;
use App\Filament\Admin\Resources\OrderResource\RelationManagers\TransactionsRelationManager\TransactionsRelationManager;
use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Models\Order\Order;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Pesanan.
 *
 * Ada empat aksi cepat di tabel, dan tiap aksi hanya muncul pada kondisi
 * yang relevan:
 *
 *   - `mark_paid`    -> pesanan belum lunas
 *   - `mark_failed`  -> pesanan belum gagal
 *   - `mark_pending` -> HANYA kalau pembayarannya gagal (kembalikan ke
 *                       pending supaya bisa diulang)
 *   - `complete`     -> pesanan sudah terkonfirmasi
 */
class OrderResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    protected static ?string $navigationIcon = AppIcon::ORDER;

    protected static ?int $navigationSort = 301;

    public static function getNavigationLabel(): string
    {
        return __('Daftar Pesanan');
    }

    public static function getModelLabel(): string
    {
        return __('Pesanan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Daftar Pesanan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('No. Pesanan'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label(__('Pelanggan'))
                    ->searchable(),
                TextColumn::make('total_price')
                    ->label(__('Total'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label(__('Status Pembayaran'))
                    ->badge(),
                TextColumn::make('booking_date')
                    ->label(__('Tanggal(fn'))
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions(static::standardTableHeaderActions())
            ->actions([
                ...static::standardTableActions(),
                static::markPaidAction(),
                static::markFailedAction(),
                static::markPendingAction(),
                static::completeAction(),
            ])
            ->bulkActions(static::standardBulkActions())
            ->paginated(false)
            ->defaultSort('id');
    }

    /**
     * Tandai pesanan lunas.
     */
    public static function markPaidAction(): Action
    {
        return Action::make('mark_paid')
            ->label(__('Tandai Lunas'))
            ->icon('/images/icons/check_circle.svg')
            ->color('success')
            ->visible(fn (Order $record): bool => $record->payment_status !== OrderPaymentStatus::PAID)
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $record->update([
                    'payment_status' => OrderPaymentStatus::PAID,
                    'status' => OrderStatus::CONFIRMED,
                ]);

                Notification::make()
                    ->title(__('Pesanan ditandai lunas.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Tandai pesanan gagal.
     */
    public static function markFailedAction(): Action
    {
        return Action::make('mark_failed')
            ->label(__('Tandai Gagal'))
            ->icon('/images/icons/x_circle.svg')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->payment_status !== OrderPaymentStatus::FAILED)
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $record->update([
                    'payment_status' => OrderPaymentStatus::FAILED,
                ]);

                Notification::make()
                    ->title(__('Pesanan ditandai gagal.'))
                    ->danger()
                    ->send();
            });
    }

    /**
     * Kembalikan pesanan yang gagal ke status pending.
     *
     * Hanya muncul untuk pesanan yang pembayarannya GAGAL -- kalau
     * pesanan masih berjalan, "kembalikan ke pending" tidak ada
     * maknanya.
     */
    public static function markPendingAction(): Action
    {
        return Action::make('mark_pending')
            ->label(__('Kembalikan ke Pending'))
            ->icon('/images/icons/refresh.svg')
            ->color('warning')
            ->visible(fn (Order $record): bool => $record->payment_status === OrderPaymentStatus::FAILED)
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $record->update([
                    'payment_status' => OrderPaymentStatus::PENDING,
                ]);

                Notification::make()
                    ->title(__('Pesanan dikembalikan ke pending.'))
                    ->warning()
                    ->send();
            });
    }

    /**
     * Selesaikan pesanan yang sudah terkonfirmasi.
     */
    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label(__('Selesaikan Pesanan'))
            ->icon('/images/icons/check_badge.svg')
            ->color('primary')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::CONFIRMED)
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $record->update([
                    'status' => OrderStatus::COMPLETED,
                ]);

                Notification::make()
                    ->title(__('Pesanan diselesaikan.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
