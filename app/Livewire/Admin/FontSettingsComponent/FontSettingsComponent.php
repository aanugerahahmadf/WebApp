<?php

namespace App\Livewire\Admin\FontSettingsComponent;

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppFont\AppFont;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

class FontSettingsComponent extends Component implements \Filament\Forms\Contracts\HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static int $sort = 10;

    public static function getSort(): int { return static::$sort; }

    public function form(Form $form): Form
    {
        $current = FmAppSetting::appearance();
        $fontFamilies = AppFont::families();

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Ukuran & Tebal Teks'))
                    ->description(__('Berlaku untuk website, Dekstop App, dan Dekstop Admin App.'))
                    ->schema([
                        Select::make('family')
                            ->label(__('Font Website, User & Welcome'))
                            ->options(array_combine($fontFamilies, $fontFamilies))
                            ->searchable()
                            ->native(false)
                            ->default($current['family'])
                            ->required(),

                        Select::make('admin_family')
                            ->label(__('Font Admin'))
                            ->options(array_combine($fontFamilies, $fontFamilies))
                            ->searchable()
                            ->native(false)
                            ->default($current['admin_family'])
                            ->required(),

                        Select::make('size')
                            ->label(__('Ukuran Huruf'))
                            ->options([
                                12 => __('Sangat kecil (12px)'),
                                14 => __('Kecil (14px)'),
                                16 => __('Sedang (16px)'),
                                18 => __('Besar (18px)'),
                                20 => __('Sangat besar (20px)'),
                                24 => __('Ekstra besar (24px)'),
                            ])
                            ->searchable()
                            ->default($current['size'])
                            ->required(),

                        Select::make('weight')
                            ->label(__('Tebal Huruf'))
                            ->options([
                                300 => __('Tipis (300)'),
                                400 => __('Normal (400)'),
                                500 => __('Medium (500)'),
                                600 => __('Semibold (600)'),
                                700 => __('Bold (700)'),
                            ])
                            ->searchable()
                            ->default($current['weight'])
                            ->required(),

                        Select::make('transform')
                            ->label(__('Kapitalisasi Teks'))
                            ->options([
                                'none' => __('Sesuai normal'),
                                'capitalize' => __('Huruf Depan Besar'),
                                'uppercase' => __('SEMUA HURUF BESAR'),
                                'lowercase' => __('semua huruf kecil'),
                            ])
                            ->searchable()
                            ->default($current['transform'])
                            ->required(),
                    ]),

                Section::make(__('Warna & Aksen'))
                    ->schema([
                        ColorPicker::make('color')->label(__('Warna Teks Utama'))->default($current['color'])->required(),
                        ColorPicker::make('accent_color')->label(__('Warna Aksen (Primary)'))->default($current['accent_color'])->required(),
                        ColorPicker::make('bg_color')->label(__('Warna Latar Body (kosongkan untuk bawaan glass)')),
                    ]),

                Section::make(__('Tata Letak & Sudut'))
                    ->schema([
                        Select::make('radius')
                            ->label(__('Radius Sudut (px)'))
                            ->options([0=>'0',4=>'4',6=>'6',8=>'8',10=>'10',12=>'12',16=>'16',20=>'20',24=>'24',32=>'32'])
                            ->searchable()
                            ->default($current['radius'])
                            ->required(),
                    ]),

                Section::make(__('Brand & Identitas Panel'))
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('brand_name')
                            ->label(__('Nama Brand yang muncul di Panel Welcome & User'))
                            ->default($current['brand_name'])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('brand_logo')
                            ->label(__('URL Logo (contoh: /images/logo.png atau URL https)'))
                            ->default($current['brand_logo']),
                    ]),

                Section::make(__('CSS Tambahan (Advanced — langsung di-inject)'))
                    ->schema([
                        Textarea::make('custom_css')
                            ->label(__('CSS khusus (opsional, admin-only)'))
                            ->rows(10)
                            ->default($current['custom_css']),
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            FmAppSetting::saveAppearance($data);
            // `FmAppSetting` tidak punya cache sendiri; cache yang dipakai
            // `AppFont::settings()` (CACHE_KEY 'app_font_settings') dihapus
            // di sini supaya panel langsung berganti font tanpa menunggu TTL.
            \App\Support\AppFont\AppFont::forgetCache();

            Notification::make()->title(__('Setelan tampilan panel berhasil disimpan!'))->success()->send();
            $this->dispatch('panel-settings-updated');
        } catch (\Exception $e) {
            Notification::make()->title(__('Gagal menyimpan setelan tampilan panel'))->body(__('Terjadi kesalahan: ') . $e->getMessage())->danger()->send();
            throw $e;
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('Livewire.Admin.font-settings.font-settings-component');
    }
}
