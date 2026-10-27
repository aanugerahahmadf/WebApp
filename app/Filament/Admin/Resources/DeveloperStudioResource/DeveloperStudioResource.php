<?php

namespace App\Filament\Admin\Resources\DeveloperStudioResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Concerns\InteractsWithGraphQLAndGrpc\InteractsWithGraphQLAndGrpc;
use App\Models\DeveloperStudio\DeveloperStudioEndpoint;
use App\Services\GraphQLExecutor\GraphQLExecutor;
use App\Support\AppIcon\AppIcon;
use App\Support\AdminAccess\AdminAccess;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeveloperStudioResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;
    use InteractsWithGraphQLAndGrpc;

    protected static ?string $model = \App\Models\DeveloperStudio\DeveloperStudioEndpoint::class;

    protected static ?string $slug = 'developer-studio';

    protected static ?string $navigationIcon = AppIcon::CODE;

    protected static ?int $navigationSort = 204;

    public static function canViewAny(): bool
    {
        return AdminAccess::allows('view_system_inventory', 'view_documentation');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::allows('manage_deployments', 'manage_documentation');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::allows('manage_deployments', 'manage_documentation');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::allows('manage_deployments', 'manage_documentation');
    }

    public static function getNavigationLabel(): string
    {
        return __('Studio API & Pengembang');
    }

    public static function getModelLabel(): string
    {
        return __('Studio API & Pengembang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Studio API & Pengembang');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return static::getAllSearchableAttributes();
    }

    public static function form(Form $form): Form
    {
        // Formnya dipakai aksi Edit baris (halaman Create punya form sendiri
        // yang membuat berkas).
        //
        // Ditulis eksplisit, BUKAN `HasModelColumns::formSchema()`: form
        // generik itu memakai nama kolom database sebagai label (`action`,
        // `middleware`, `controller_class`), dan kolom `middleware` yang
        // di-cast array disodorkan ke Textarea mentah -- nilainya tidak
        // pernah bisa dibaca/ditulis dengan benar.
        return $form->schema([

            Section::make(__('Detail Endpoint'))
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label(__('Tipe'))
                        ->options(static::typeOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('method')
                        ->label(__('Metode'))
                        ->required(),
                    TextInput::make('uri')
                        ->label(__('URI / Alamat'))
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label(__('Nama')),
                    TextInput::make('action')
                        ->label(__('Aksi / Handler')),
                    TextInput::make('controller_class')
                        ->label(__('Controller')),
                    TextInput::make('domain')
                        ->label(__('Domain')),
                    Select::make('status')
                        ->label(__('Status'))
                        ->options([
                            'active' => __('Aktif'),
                            'inactive' => __('Tidak Aktif'),
                        ])
                        ->searchable(),
                    TextInput::make('file_path')
                        ->label(__('Berkas'))
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('Deskripsi'))
                        ->columnSpanFull(),
                    Textarea::make('middleware')
                        ->label(__('Middleware'))
                        // Kolomnya array (cast), sedangkan Textarea bekerja
                        // dengan string: satu middleware per baris.
                        ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state)
                        ->dehydrateStateUsing(fn ($state): array => array_values(array_filter(array_map(
                            'trim',
                            preg_split('/\r\n|\r|\n/', (string) $state) ?: []
                        ))))
                        ->helperText(__('Satu middleware per baris.'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Label tipe endpoint. Tipe mentah di database (`api`, `web`, ...)
     * diterjemahkan di sini supaya tabel tidak menampilkan kode.
     */
    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'api' => __('REST API'),
            'web' => __('Web'),
            'admin' => __('Panel Admin'),
            'controller' => __('Controller'),
            'graphql' => __('GraphQL'),
            'grpc' => __('gRPC'),
            'capacitor' => __('Capacitor'),
            'flutter' => __('Flutter'),
            'python' => __('Python / AI Core'),
            default => $type ? ucfirst($type) : '-',
        };
    }

    public static function typeColor(?string $type): string
    {
        return match ($type) {
            'api' => 'info',
            'web' => 'gray',
            'admin' => 'warning',
            'controller' => 'primary',
            'graphql' => 'success',
            'grpc' => 'danger',
            'capacitor' => 'info',
            'flutter' => 'primary',
            'python' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return DeveloperStudioEndpoint::query()
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type', 'type')
            ->mapWithKeys(fn (string $type): array => [$type => static::typeLabel($type)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function methodOptions(): array
    {
        return DeveloperStudioEndpoint::query()
            ->select('method')
            ->whereNotNull('method')
            ->distinct()
            ->orderBy('method')
            ->pluck('method', 'method')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::typeLabel($state))
                    ->color(fn (?string $state): string => static::typeColor($state))
                    ->sortable(),

                TextColumn::make('method')
                    ->label(__('Metode'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('uri')
                    ->label(__('URI / Alamat'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->wrap()
                    ->fontFamily('mono')
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label(__('Nama'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->wrap()
                    ->placeholder('-'),

                TextColumn::make('action')
                    ->label(__('Aksi / Handler'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => __('Aktif'),
                        'inactive' => __('Tidak Aktif'),
                        null, '' => '-',
                        default => $state,
                    })
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('middleware')
                    ->label(__('Middleware'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('controller_class')
                    ->label(__('Controller'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->limit(45)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('file_path')
                    ->label(__('Berkas'))
                    ->formatStateUsing(fn (?string $state): ?string => $state)
                    ->limit(45)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordUrl(fn (DeveloperStudioEndpoint $record): string => static::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Tipe'))
                    ->options(fn (): array => static::typeOptions())
                    ->searchable(),

                SelectFilter::make('method')
                    ->label(__('Metode'))
                    ->options(fn (): array => static::methodOptions())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Aktif'),
                        'inactive' => __('Tidak Aktif'),
                    ])
                    ->searchable(),
            ])
            ->actions([
                // 611 baris: tombol tunggal, BUKAN ActionGroup dropdown.
                // Dropdown per baris membuat tabel ini butuh >10 detik hanya
                // untuk merender tombolnya. View tetap ada lewat klik baris
                // (`recordUrl`) yang membuka halaman detail lengkap.
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete')),

                // Uji GraphQL: hanya untuk record bertipe graphql.
                Action::make('test_graphql')
                    ->label(__('Uji GraphQL'))
                    ->icon('/images/icons/play.svg')
                    ->color('primary')
                    ->visible(fn (DeveloperStudioEndpoint $record): bool => $record->type === 'graphql')
                    ->action(function (DeveloperStudioEndpoint $record): void {
                        // `$this` tidak tersedia di kelas resource (kelasnya
                        // statis), jadi executor-nya dipanggil langsung --
                        // bukan lewat trait `InteractsWithGraphQLAndGrpc`
                        // yang metodenya bukan statis.
                        try {
                            $result = app(GraphQLExecutor::class)->execute('{ __typename }');
                        } catch (\Throwable $exception) {
                            $result = ['data' => null, 'errors' => [['message' => $exception->getMessage()]]];
                        }

                        Notification::make()
                            ->success()
                            ->title(__('Uji GraphQL Selesai'))
                            ->body((string) json_encode($result['data'] ?? $result['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->send();
                    }),

                // Uji gRPC: hanya untuk record bertipe grpc.
                Action::make('test_grpc')
                    ->label(__('Uji gRPC'))
                    ->icon('/images/icons/play.svg')
                    ->color('primary')
                    ->visible(fn (DeveloperStudioEndpoint $record): bool => $record->type === 'grpc')
                    ->action(function (DeveloperStudioEndpoint $record): void {
                        $health = static::checkApiEnginesHealth();

                        Notification::make()
                            ->success()
                            ->title(__('Uji gRPC Selesai'))
                            ->body(__('Status: :status | Proto ditemukan: :proto', [
                                'status' => $health['grpc']['status'] ?? '-',
                                'proto' => $health['grpc']['proto_count'] ?? 0,
                            ]))
                            ->send();
                    }),
            ])
            ->bulkActions(static::standardBulkActions())
            ->striped()
            ->paginated(false)
            ->defaultSort('id');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeveloperStudio\ListDeveloperStudio::route('/'),
            'create' => Pages\CreateDeveloperStudio\CreateDeveloperStudio::route('/create'),
            'view' => Pages\ViewDeveloperStudio\ViewDeveloperStudio::route('/{record}'),
        ];
    }
}
