<?php

namespace App\Filament\Admin\Resources\ReportResource;

use App\Enums\ReportStatus\ReportStatus;
use App\Filament\Admin\Resources\ReportResource\Pages;
use App\Models\Report\Report;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $slug = 'reports';

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reason';

    public static function getModelLabel(): string
    {
        return __('Laporan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Laporan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Manajemen');
    }

    public static function getNavigationLabel(): string
    {
        return __('Laporan Pengguna');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Report::where('status', ReportStatus::OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Jumlah laporan baru');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Detail Laporan'))
                    ->schema([
                        Forms\Components\TextInput::make('user.full_name')
                            ->label(__('Pelapor'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('category')
                            ->label(__('Kategori'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('reason')
                            ->label(__('Alasan'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Deskripsi'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('attachments')
                            ->label(__('Lampiran foto/video'))
                            ->disk('public')
                            ->multiple()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label(__('Status'))
                            ->options(collect(ReportStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->toArray())
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('Pelapor'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('Kategori'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'product' => __('Produk'),
                        'package' => __('Paket'),
                        'vendor' => __('Vendor'),
                        'order' => __('Pesanan'),
                        'review' => __('Ulasan'),
                        'general' => __('Umum'),
                        'bug_report' => __('Bug / Kendala Aplikasi'),
                        'account_issue' => __('Masalah Akun'),
                        'order_help' => __('Bantuan Pesanan'),
                        'payment_issue' => __('Masalah Pembayaran'),
                        'decor_consultation' => __('Konsultasi Dekorasi'),
                        'general_question' => __('Pertanyaan Umum'),
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'product', 'package', 'account_issue', 'general_question' => 'info',
                        'vendor', 'bug_report' => 'warning',
                        'order', 'order_help', 'decor_consultation' => 'success',
                        'review', 'payment_issue' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('Alasan'))
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Deskripsi'))
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Tanggal'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(collect(ReportStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->toArray()),
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('Kategori'))
                    ->options([
                        'product' => __('Produk'),
                        'package' => __('Paket'),
                        'vendor' => __('Vendor'),
                        'order' => __('Pesanan'),
                        'review' => __('Ulasan'),
                        'general' => __('Umum'),
                        'bug_report' => __('Bug / Kendala Aplikasi'),
                        'account_issue' => __('Masalah Akun'),
                        'order_help' => __('Bantuan Pesanan'),
                        'payment_issue' => __('Masalah Pembayaran'),
                        'decor_consultation' => __('Konsultasi Dekorasi'),
                        'general_question' => __('Pertanyaan Umum'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->button()
                    ->color('info'),
                Tables\Actions\Action::make('downloadPdf')
                    ->label(__('Unduh PDF'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()
                    ->color('success')
                    ->action(function (Report $record) {
                        $userName = e($record->user?->full_name ?? '-');
                        $category = e($record->category);
                        $reason = e($record->reason ?? '-');
                        $description = nl2br(e($record->description ?? '-'));
                        $createdAt = e($record->created_at?->format('d/m/Y H:i') ?? '-');
                        $attachments = collect($record->attachment_urls)->map(fn ($url) => '<p><a href="'.e($url).'">'.e($url).'</a></p>')->implode('');
                        $html = "<html><meta charset='utf-8'><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}h1{font-size:20px}img{max-width:100%}</style><h1>Laporan Pengguna #{$record->id}</h1><p><b>Pelapor:</b> {$userName}</p><p><b>Kategori:</b> {$category}</p><p><b>Alasan:</b> {$reason}</p><p><b>Tanggal:</b> {$createdAt}</p><p><b>Deskripsi:</b><br>{$description}</p><h3>Lampiran</h3>{$attachments}</html>";
                        $pdf = new \Dompdf\Dompdf;
                        $pdf->loadHtml($html, 'UTF-8');
                        $pdf->setPaper('A4', 'portrait');
                        $pdf->render();

                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'laporan-'.$record->id.'.pdf',
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning')
                    ->label(__('Ubah Status'))
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Laporan diperbarui'))
                            ->body(__('Status laporan telah diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReports\ManageReports::route('/'),
        ];
    }
}
