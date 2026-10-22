<?php

namespace App\Livewire\Admin\WebsiteMobileCustomizerComponent;

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppFont\AppFont;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Komponen Livewire untuk halaman "Kustomisasi Website, Mobile Android & iOS".
 *
 * Semua pengaturan disimpan di tabel `fm_app_settings` (key/value, value JSON).
 * Komponen ini membaca keempat kunci yang ada (`appearance`, `mobile_flutter`,
 * `mobile_capacitor`, dan empat `maintenance_mobile_*`) lalu menyimpannya
 * kembali lewat `FmAppSetting::saveAppearance()` dan `firstOrCreate()` per key.
 *
 * Kenapa satu komponen, bukan empat halaman terpisah: admin biasanya mengubah
 * brand & warna sekali jalan untuk semua platform, jadi satu halaman dengan
 * beberapa section lebih cepat daripada berpindah-pindah menu.
 */
class WebsiteMobileCustomizerComponent extends Component implements \Filament\Forms\Contracts\HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'appearance' => FmAppSetting::appearance(),
            'mobile_flutter' => FmAppSetting::where('key', 'mobile_flutter')->value('value') ?? [],
            'mobile_capacitor' => FmAppSetting::where('key', 'mobile_capacitor')->value('value') ?? [],
            'maintenance' => [
                'flutter_android' => FmAppSetting::where('key', 'maintenance_mobile_flutter_android')->value('value') ?? [],
                'flutter_ios' => FmAppSetting::where('key', 'maintenance_mobile_flutter_ios')->value('value') ?? [],
                'capacitor_android' => FmAppSetting::where('key', 'maintenance_mobile_capacitor_android')->value('value') ?? [],
                'capacitor_ios' => FmAppSetting::where('key', 'maintenance_mobile_capacitor_ios')->value('value') ?? [],
            ],
        ];

        // Pastikan array (kalau value-nya null/ string).
        foreach (['mobile_flutter', 'mobile_capacitor'] as $k) {
            if (! is_array($this->data[$k])) {
                $this->data[$k] = [];
            }
        }
        foreach (array_keys($this->data['maintenance']) as $mk) {
            if (! is_array($this->data['maintenance'][$mk])) {
                $this->data['maintenance'][$mk] = [];
            }
        }

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        $appearance = FmAppSetting::appearance();
        $fontFamilies = AppFont::families();

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Tampilan Website & Panel Admin'))
                    ->description(__('Warna, radius, brand, dan CSS tambahan yang berlaku untuk website, Dekstop App, dan panel admin.'))
                    ->schema([
                        Select::make('appearance.family')
                            ->label(__('Huruf Website & Dekstop App'))
                            ->options(array_combine($fontFamilies, $fontFamilies))
                            ->searchable()
                            ->default($appearance['family'])
                            ->required(),

                        Select::make('appearance.admin_family')
                            ->label(__('Huruf Panel Admin & Dekstop Admin App'))
                            ->options(array_combine($fontFamilies, $fontFamilies))
                            ->searchable()
                            ->default($appearance['admin_family'])
                            ->required(),

                        Select::make('appearance.size')
                            ->label(__('Ukuran Huruf (px)'))
                            ->options([10=>'10',12=>'12',14=>'14',16=>'16',18=>'18',20=>'20',24=>'24',28=>'28',32=>'32'])
                            ->searchable()
                            ->default($appearance['size'])
                            ->required(),

                        Select::make('appearance.weight')
                            ->label(__('Tebal Huruf'))
                            ->options([
                                300 => __('300 (Tipis)'),
                                400 => __('400 (Normal)'),
                                500 => __('500 (Medium)'),
                                600 => __('600 (Semibold)'),
                                700 => __('700 (Bold)'),
                                800 => __('800 (Extra Bold)'),
                            ])
                            ->searchable()
                            ->default($appearance['weight'])
                            ->required(),

                        Select::make('appearance.transform')
                            ->label(__('Kapitalisasi Teks'))
                            ->options([
                                'none' => __('Sesuai normal'),
                                'capitalize' => __('Huruf Depan Besar'),
                                'uppercase' => __('SEMUA HURUF BESAR'),
                                'lowercase' => __('semua huruf kecil'),
                            ])
                            ->searchable()
                            ->default($appearance['transform'])
                            ->required(),

                        ColorPicker::make('appearance.color')
                            ->label(__('Warna Teks Utama'))
                            ->default($appearance['color'])
                            ->required(),

                        ColorPicker::make('appearance.accent_color')
                            ->label(__('Warna Aksen (Primary)'))
                            ->default($appearance['accent_color'])
                            ->required(),

                        ColorPicker::make('appearance.bg_color')
                            ->label(__('Warna Latar Body (kosong = bawaan glass)'))
                            ->default($appearance['bg_color']),

                        Select::make('appearance.radius')
                            ->label(__('Radius Sudut (px)'))
                            ->options([0=>'0',2=>'2',4=>'4',6=>'6',8=>'8',10=>'10',12=>'12',16=>'16',20=>'20',24=>'24',32=>'32'])
                            ->searchable()
                            ->default($appearance['radius'])
                            ->required(),

                        TextInput::make('appearance.brand_name')
                            ->label(__('Nama Brand (Panel Welcome & User)'))
                            ->default($appearance['brand_name'])
                            ->required()
                            ->maxLength(100),

                        TextInput::make('appearance.brand_logo')
                            ->label(__('URL Logo (contoh: /images/logo.png)'))
                            ->default($appearance['brand_logo'])
                            ->maxLength(2048),

                        Textarea::make('appearance.custom_css')
                            ->label(__('CSS Tambahan (Advanced)'))
                            ->rows(8)
                            ->default($appearance['custom_css']),
                    ])->columns(2),

                Section::make(__('Mobile Android (Flutter)'))
                    ->description(__('Pengaturan aplikasi mobile Flutter untuk Android.'))
                    ->schema([
                        TextInput::make('mobile_flutter.app_name')->label(__('Nama Aplikasi'))->default($this->data['mobile_flutter']['app_name'] ?? 'Dekorasi Bunga Pernikahan'),
                        TextInput::make('mobile_flutter.tagline')->label(__('Tagline'))->default($this->data['mobile_flutter']['tagline'] ?? ''),
                        ColorPicker::make('mobile_flutter.primary_color')->label(__('Warna Utama'))->default($this->data['mobile_flutter']['primary_color'] ?? '#6366f1'),
                        TextInput::make('mobile_flutter.splash_tagline')->label(__('Tagline Splash Screen'))->default($this->data['mobile_flutter']['splash_tagline'] ?? ''),
                        TextInput::make('mobile_flutter.play_store_url')->label(__('URL Play Store'))->default($this->data['mobile_flutter']['play_store_url'] ?? '')->url(),
                        TextInput::make('mobile_flutter.app_store_url')->label(__('URL App Store'))->default($this->data['mobile_flutter']['app_store_url'] ?? '')->url(),
                        TextInput::make('mobile_flutter.min_app_version')->label(__('Versi Minimum Aplikasi'))->default($this->data['mobile_flutter']['min_app_version'] ?? '1.0.0'),
                        Toggle::make('mobile_flutter.force_update')->label(__('Paksa Update'))->default($this->data['mobile_flutter']['force_update'] ?? false),
                    ])->columns(2),

                Section::make(__('Mobile iOS (Capacitor Shell)'))
                    ->description(__('Pengaturan aplikasi mobile Capacitor untuk iOS.'))
                    ->schema([
                        TextInput::make('mobile_capacitor.app_name')->label(__('Nama Aplikasi'))->default($this->data['mobile_capacitor']['app_name'] ?? 'Dekorasi Bunga Mobile Shell'),
                        TextInput::make('mobile_capacitor.tagline')->label(__('Tagline'))->default($this->data['mobile_capacitor']['tagline'] ?? ''),
                        ColorPicker::make('mobile_capacitor.primary_color')->label(__('Warna Utama'))->default($this->data['mobile_capacitor']['primary_color'] ?? '#4f46e5'),
                        TextInput::make('mobile_capacitor.splash_tagline')->label(__('Tagline Splash Screen'))->default($this->data['mobile_capacitor']['splash_tagline'] ?? ''),
                        TextInput::make('mobile_capacitor.play_store_url')->label(__('URL Play Store'))->default($this->data['mobile_capacitor']['play_store_url'] ?? '')->url(),
                        TextInput::make('mobile_capacitor.app_store_url')->label(__('URL App Store'))->default($this->data['mobile_capacitor']['app_store_url'] ?? '')->url(),
                        TextInput::make('mobile_capacitor.min_app_version')->label(__('Versi Minimum Aplikasi'))->default($this->data['mobile_capacitor']['min_app_version'] ?? '1.0.0'),
                        Toggle::make('mobile_capacitor.force_update')->label(__('Paksa Update'))->default($this->data['mobile_capacitor']['force_update'] ?? false),
                    ])->columns(2),

                Section::make(__('Mode Pemeliharaan (Mobile)'))
                    ->description(__('Aktifkan maintenance mode untuk platform mobile tertentu.'))
                    ->schema([
                        $this->maintenanceSection('flutter_android', __('Mobile Android (Flutter)')),
                        $this->maintenanceSection('flutter_ios', __('Mobile iOS (Flutter)')),
                        $this->maintenanceSection('capacitor_android', __('Mobile Android (Capacitor)')),
                        $this->maintenanceSection('capacitor_ios', __('Mobile iOS (Capacitor)')),
                    ]),
            ]);
    }

    protected function maintenanceSection(string $key, string $label): Section
    {
        $prefix = "maintenance.{$key}";

        return Section::make($label)
            ->schema([
                Toggle::make("{$prefix}.is_active")->label(__('Aktif'))->default($this->data['maintenance'][$key]['is_active'] ?? false),
                Textarea::make("{$prefix}.message")->label(__('Pesan Maintenance'))->rows(2)->default($this->data['maintenance'][$key]['message'] ?? ''),
                TextInput::make("{$prefix}.bypass_secret")->label(__('Bypass Secret'))->default($this->data['maintenance'][$key]['bypass_secret'] ?? ''),
                TextInput::make("{$prefix}.allowed_ips")->label(__('IP yang Diizinkan'))->default($this->data['maintenance'][$key]['allowed_ips'] ?? '127.0.0.1'),
                TextInput::make("{$prefix}.http_status")->label(__('HTTP Status'))->numeric()->default($this->data['maintenance'][$key]['http_status'] ?? 503),
            ])->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Appearance
        if (isset($data['appearance']) && is_array($data['appearance'])) {
            FmAppSetting::saveAppearance($data['appearance']);
            AppFont::forgetCache();
        }

        // Mobile settings
        foreach (['mobile_flutter', 'mobile_capacitor'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $setting = FmAppSetting::firstOrNew(['key' => $key]);
                $setting->value = array_merge($this->data[$key] ?? [], $data[$key]);
                $setting->save();
            }
        }

        // Maintenance
        $map = [
            'flutter_android' => 'maintenance_mobile_flutter_android',
            'flutter_ios' => 'maintenance_mobile_flutter_ios',
            'capacitor_android' => 'maintenance_mobile_capacitor_android',
            'capacitor_ios' => 'maintenance_mobile_capacitor_ios',
        ];
        foreach ($map as $short => $fullKey) {
            if (isset($data['maintenance'][$short]) && is_array($data['maintenance'][$short])) {
                $setting = FmAppSetting::firstOrNew(['key' => $fullKey]);
                $setting->value = array_merge($this->data['maintenance'][$short] ?? [], $data['maintenance'][$short]);
                $setting->save();
            }
        }

        Notification::make()->title(__('Pengaturan berhasil disimpan!'))->success()->send();
        $this->dispatch('customizer-saved');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('Livewire.Admin.website-mobile-customizer.website-mobile-customizer-component');
    }
}
