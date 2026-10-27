<?php

namespace App\Filament\Admin\Resources\BackgroundResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackgroundResource extends Resource
{
    use HasModelColumns;

    protected static ?string $model = FmAppSetting::class;
    protected static ?string $slug = 'background';
    protected static ?string $navigationIcon = AppIcon::BRUSH;
    protected static ?int $navigationSort = 203;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationLabel(): string { return __('Background'); }
    public static function getModelLabel(): string { return __('Background'); }
    public static function getPluralModelLabel(): string { return __('Background'); }
    public static function getNavigationGroup(): ?string { return __('Pengaturan Sistem'); }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('key', 'background');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('Background User & Welcome'))
                ->description(__('Atur gambar/video dan gerakan background untuk panel User dan Welcome.'))
                ->columns(2)
                ->schema([
                    Toggle::make('value.active')->label(__('Aktif'))->default(false),
                    Select::make('value.type')->label(__('Jenis Media'))->options([
                        'image' => __('Gambar'),
                        'video' => __('Video'),
                    ])->required()->live(),
                    FileUpload::make('value.image')->label(__('Background Gambar'))
                        ->image()->disk('public')->directory('backgrounds')->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->visible(fn ($get) => $get('value.type') === 'image'),
                    FileUpload::make('value.video')->label(__('Background Video'))
                        ->disk('public')->directory('backgrounds')->visibility('public')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                        ->visible(fn ($get) => $get('value.type') === 'video'),
                    ColorPicker::make('value.overlay')->label(__('Warna Overlay'))->default('#000000'),
                    TextInput::make('value.overlay_opacity')->label(__('Opacity Overlay (%)'))
                        ->numeric()
                        ->minValue(0)->maxValue(100)->step(1)->default(20)->suffix('%'),
                    TextInput::make('value.blur')->label(__('Blur Background (px)'))
                        ->numeric()
                        ->minValue(0)->maxValue(40)->step(1)->default(2)->suffix('px')
                        ->helperText(__('0 = tanpa blur. Nilai 1-3 cocok untuk background foto; jangan terlalu tinggi.')),
                    TextInput::make('value.parallax_strength')->label(__('Kekuatan Gerak Kursor'))
                        ->numeric()
                        ->minValue(0)->maxValue(60)->step(1)->default(18)->suffix('px'),
                    TextInput::make('value.parallax_speed')->label(__('Kecepatan Respons'))
                        ->numeric()
                        ->minValue(0.02)->maxValue(0.5)->step(0.01)->default(0.12),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label(__('Pengaturan')),
            TextColumn::make('value.type')->label(__('Media')),
            TextColumn::make('value.active')->label(__('Status'))->boolean(),
            TextColumn::make('updated_at')->label(__('Diperbarui'))->dateTime(),
        ])->actions([EditAction::make()])->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBackground::route('/')];
    }
}
