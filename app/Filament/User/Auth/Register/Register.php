<?php

namespace App\Filament\User\Auth\Register;

use App\Forms\Components\BirthPlaceDatePicker\BirthPlaceDatePicker;
use App\Forms\Components\CalendarPicker\CalendarPicker;
use App\Models\User\User;
use App\Services\GeoLocationService\GeoLocationService;
use App\Services\GeoNamesService\GeoNamesService;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Services\WorldRegionService\WorldRegionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravolt\Indonesia\Models\City as IndonesiaCity;
use Laravolt\Indonesia\Models\District as IndonesiaDistrict;
use Laravolt\Indonesia\Models\Province as IndonesiaProvince;
use Laravolt\Indonesia\Models\Village as IndonesiaVillage;
use Spatie\Permission\Models\Role;

class Register extends BaseRegister
{
    public function getView(): string
    {
        return 'User.auth.register.register';
    }

    public function getHeading(): string|Htmlable
    {
        return __('Daftar Akun Baru');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label(__('Alamat Email'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Foto Profil ─────────────────────────────────────────────
                Section::make(__('Foto Profil'))
                    ->icon('heroicon-o-camera')
                    ->description(__('Foto profil Anda. Buka kamera atau pilih dari galeri.'))
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label('')
                            ->image()
                            ->avatar()
                            ->directory('avatars')
                            ->alignCenter()
                            ->columnSpanFull()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->maxSize(5120)
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1'])
                            ->extraAttributes(['class' => 'flex flex-col items-center justify-center'])
                            ->extraInputAttributes([
                                'accept' => 'image/*',
                                'class' => 'avatar-file-input',
                            ])
                            ->extraFieldWrapperAttributes(['class' => 'avatar-upload-centered']),
                    ])
                    ->columns(1),

                // ── Akun ────────────────────────────────────────────────────
                Section::make(__('Akun'))
                    ->icon('heroicon-o-user-circle')
                    ->description(__('Username, email, dan kata sandi untuk masuk.'))
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->unique(User::class)
                            ->autocomplete('username')
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('Alamat Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class)
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label(__('Kata Sandi'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(12)
                                ->letters()
                                ->mixedCase()
                                ->numbers()
                                ->symbols()
                            )
                            ->same('password_confirmation')
                            ->helperText(__('Minimal 12 karakter: huruf besar, huruf kecil, angka, dan simbol.'))
                            ->validationAttribute(__('Kata Sandi'))
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('password_strength', $this->passwordStrength($state))),
                        TextInput::make('password_confirmation')
                            ->label(__('Konfirmasi Kata Sandi'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->columns(1),

                // ── Nama Lengkap ────────────────────────────────────────────
                Section::make(__('Nama Lengkap'))
                    ->icon('heroicon-o-identification')
                    ->description(__('Nama sesuai dokumen identitas Anda.'))
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('Nama Depan'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('full_name', $this->joinFullName($get))),
                        TextInput::make('mid_name')
                            ->label(__('Nama Tengah'))
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('full_name', $this->joinFullName($get))),
                        TextInput::make('last_name')
                            ->label(__('Nama Belakang'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('full_name', $this->joinFullName($get))),
                        TextInput::make('full_name')
                            ->label(__('Preview Nama Lengkap'))
                            ->readOnly()
                            ->dehydrated(false),
                    ])
                    ->columns(1),

                // ── Nomor WhatsApp ──────────────────────────────────────────
                Section::make(__('Nomor WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->description(__('Untuk notifikasi pembayaran via WhatsApp.'))
                    ->schema([
                        TextInput::make('whatsapp')
                            ->label(__('Nomor WhatsApp'))
                            ->tel()
                            ->required()
                            ->maxLength(255)
                            ->prefix('+62'),
                    ])
                    ->columns(1),

                // ── Tempat & Tanggal Lahir ──────────────────────────────────
                Section::make(__('Tempat & Tanggal Lahir'))
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        BirthPlaceDatePicker::make('birth_place_date')
                            ->label(__('Tempat & Tanggal Lahir'))
                            ->required()
                            ->semiboldAll()
                            ->columnSpanFull(),
                    ]),

                // ── Identitas & Dokumen ─────────────────────────────────────
                Section::make(__('Identitas & Dokumen'))
                    ->icon('heroicon-o-shield-check')
                    ->description(__('Pilih jenis identitas, lalu scan atau unggah foto dokumen dan selfie.'))
                    ->schema([
                        Select::make('identity_type')
                            ->label(__('Jenis Identitas'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->default('ktp')
                            ->options([
                                'ktp' => __('Kartu Tanda Kependudukan (KTP)'),
                                'passport' => __('Passport'),
                                'sim' => __('Surat Izin Mengemudi (SIM)'),
                                'npwp' => __('Nomor Pokok Wajib Pajak (NPWP)'),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('ktp_number')
                            ->label(__('Nomor KTP'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'ktp')
                            ->maxLength(20)
                            ->numeric()
                            ->columnSpanFull(),
                        TextInput::make('passport_number')
                            ->label(__('Nomor Passport'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'passport')
                            ->maxLength(20)
                            ->columnSpanFull(),
                        TextInput::make('sim_number')
                            ->label(__('Nomor SIM'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'sim')
                            ->maxLength(20)
                            ->columnSpanFull(),
                        TextInput::make('npwp_number')
                            ->label(__('Nomor NPWP'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'npwp')
                            ->maxLength(20)
                            ->columnSpanFull(),
                        FileUpload::make('ktp_photo')
                            ->label(fn (Get $get) => match ($get('identity_type')) {
                                'ktp' => __('Foto KTP'),
                                'passport' => __('Foto Passport'),
                                'sim' => __('Foto SIM'),
                                'npwp' => __('Foto NPWP'),
                                default => __('Foto Identitas'),
                            })
                            ->image()
                            ->maxSize(5120)
                            ->directory('ktp-photos')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->extraInputAttributes(['accept' => 'image/*'])
                            ->extraFieldWrapperAttributes(['class' => 'document-photo-wrapper'])
                            ->helperText(__('Scan atau unggah foto dokumen identitas Anda dari kamera atau galeri.'))
                            ->columnSpanFull(),
                        FileUpload::make('selfie_photo')
                            ->label(fn (Get $get) => match ($get('identity_type')) {
                                'ktp' => __('Foto Selfie + KTP'),
                                'passport' => __('Foto Selfie + Passport'),
                                'sim' => __('Foto Selfie + SIM'),
                                'npwp' => __('Foto Selfie + NPWP'),
                                default => __('Foto Selfie + Identitas'),
                            })
                            ->image()
                            ->maxSize(5120)
                            ->directory('selfies')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->extraInputAttributes(['accept' => 'image/*'])
                            ->extraFieldWrapperAttributes(['class' => 'selfie-photo-wrapper'])
                            ->helperText(__('Foto diri Anda sambil memegang dokumen identitas.'))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // ── Alamat ──────────────────────────────────────────────────
                Section::make(__('Alamat'))
                    ->icon('heroicon-o-home')
                    ->schema([
                        Select::make('country')
                            ->label(__('Negara'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(config('countries'))
                            ->default('Indonesia')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Negara'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name'])
                            ->columnSpanFull(),
                        // Indonesia: cascading dropdown
                        Select::make('province_id')
                            ->label(__('Provinsi'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn () => IndonesiaProvince::pluck('name', 'id'))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Provinsi'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $maxCode = (int) IndonesiaProvince::max('code');
                                $code = $maxCode > 0 ? (string) ($maxCode + 1) : '99';
                                $province = IndonesiaProvince::create([
                                    'code' => $code,
                                    'name' => strtoupper($data['name']),
                                ]);
                                return $province->id;
                            })
                            ->afterStateUpdated(function (Set $set) {
                                $set('city_id', null);
                                $set('district_id', null);
                                $set('village_id', null);
                                $set('province_name', null);
                                $set('city_name', null);
                                $set('district_name', null);
                                $set('village_name', null);
                            }),
                        Select::make('city_id')
                            ->label(__('Kota / Kabupaten'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn (Get $get) => $get('province_id')
                                ? IndonesiaCity::where('province_code', IndonesiaProvince::find($get('province_id'))?->code)
                                    ->pluck('name', 'id')
                                : [])
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Kota / Kabupaten'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, Get $get): ?int {
                                $province = IndonesiaProvince::find($get('province_id'));
                                $provinceCode = $province?->code ?? '99';
                                $maxCode = (int) IndonesiaCity::where('province_code', $provinceCode)->max('code');
                                $code = $maxCode > 0 ? (string) ($maxCode + 1) : $provinceCode . '99';
                                $city = IndonesiaCity::create([
                                    'code' => $code,
                                    'province_code' => $provinceCode,
                                    'name' => strtoupper($data['name']),
                                ]);
                                return $city->id;
                            })
                            ->afterStateUpdated(function (Set $set) {
                                $set('district_id', null);
                                $set('village_id', null);
                                $set('city_name', null);
                                $set('district_name', null);
                                $set('village_name', null);
                            }),
                        Select::make('district_id')
                            ->label(__('Kecamatan'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn (Get $get) => $get('city_id')
                                ? IndonesiaDistrict::where('city_code', IndonesiaCity::find($get('city_id'))?->code)
                                    ->pluck('name', 'id')
                                : [])
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Kecamatan'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, Get $get): ?int {
                                $city = IndonesiaCity::find($get('city_id'));
                                $cityCode = $city?->code ?? '9999';
                                $maxCode = (int) IndonesiaDistrict::where('city_code', $cityCode)->max('code');
                                $code = $maxCode > 0 ? (string) ($maxCode + 1) : $cityCode . '99';
                                $district = IndonesiaDistrict::create([
                                    'code' => $code,
                                    'city_code' => $cityCode,
                                    'name' => strtoupper($data['name']),
                                ]);
                                return $district->id;
                            })
                            ->afterStateUpdated(function (Set $set) {
                                $set('village_id', null);
                                $set('district_name', null);
                                $set('village_name', null);
                            }),
                        Select::make('village_id')
                            ->label(__('Kelurahan / Desa'))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn (Get $get) => $get('district_id')
                                ? IndonesiaVillage::where('district_code', IndonesiaDistrict::find($get('district_id'))?->code)
                                    ->pluck('name', 'id')
                                : [])
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Kelurahan / Desa'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, Get $get): ?int {
                                $district = IndonesiaDistrict::find($get('district_id'));
                                $districtCode = $district?->code ?? '999999';
                                $maxCode = (int) IndonesiaVillage::where('district_code', $districtCode)->max('code');
                                $code = $maxCode > 0 ? (string) ($maxCode + 1) : $districtCode . '9999';
                                $village = IndonesiaVillage::create([
                                    'code' => $code,
                                    'district_code' => $districtCode,
                                    'name' => strtoupper($data['name']),
                                ]);
                                return $village->id;
                            }),
                        // Non-Indonesia: real data from WorldRegionService
                        Select::make('province_name')
                            ->label(__('Provinsi'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $country = $get('country');
                                if (! $country) {
                                    return [];
                                }
                                $states = app(WorldRegionService::class)->getStates($country);

                                return collect($states)
                                    ->when($search, fn ($col) => $col->filter(fn ($s) => str_contains(
                                        strtolower($s['name'] ?? $s),
                                        strtolower($search)
                                    )))
                                    ->pluck('name', 'name')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->afterStateUpdated(fn (Set $set) => $set('city_name', null))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        Select::make('city_name')
                            ->label(__('Kota / Kabupaten'))
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $country = $get('country');
                                $state = $get('province_name');
                                if (! $country || ! $state) {
                                    return [];
                                }
                                $cities = app(WorldRegionService::class)->getCities($country, $state);

                                return collect($cities)
                                    ->when($search, fn ($col) => $col->filter(fn ($c) => str_contains(
                                        strtolower($c),
                                        strtolower($search)
                                    )))
                                    ->mapWithKeys(fn ($city) => [$city => $city])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        Select::make('district_name')
                            ->label(__('Kecamatan'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $country = $get('country');
                                $state = $get('province_name');
                                if (! $country || ! $state) {
                                    return $search ? [$search => $search] : [];
                                }
                                $districts = app(GeoNamesService::class)->getAdmin2ByStateName($country, $state);
                                $results = collect($districts)
                                    ->when($search, fn ($col) => $col->filter(fn ($d) => str_contains(
                                        strtolower($d['name']),
                                        strtolower($search)
                                    )))
                                    ->pluck('name', 'name')
                                    ->toArray();
                                if (empty($results) && $search) {
                                    return [$search => $search];
                                }

                                return $results;
                            })
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->afterStateUpdated(fn (Set $set) => $set('village_name', null))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        Select::make('village_name')
                            ->label(__('Kelurahan / Desa'))
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $country = $get('country');
                                $state = $get('province_name');
                                $district = $get('district_name');
                                if (! $country || ! $state || ! $district) {
                                    return $search ? [$search => $search] : [];
                                }
                                $villages = app(GeoNamesService::class)->getAdmin3ByDistrictName($country, $state, $district);
                                $results = collect($villages)
                                    ->when($search, fn ($col) => $col->filter(fn ($v) => str_contains(
                                        strtolower($v['name']),
                                        strtolower($search)
                                    )))
                                    ->pluck('name', 'name')
                                    ->toArray();
                                if (empty($results) && $search) {
                                    return [$search => $search];
                                }

                                return $results;
                            })
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        TextInput::make('postal_code')
                            ->label(__('Kode Pos'))
                            ->maxLength(10)
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia'),
                        Select::make('postal_code_international')
                            ->label(__('Kode Pos'))
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $country = $get('country');
                                $city = $get('city_name');
                                if (! $country || ! $city) {
                                    return $search ? [$search => $search] : [];
                                }
                                $codes = app(GeoNamesService::class)->searchPostalCodes($country, $city);
                                $results = collect($codes)
                                    ->when($search, fn ($col) => $col->filter(fn ($p) => str_contains(
                                        $p['postal_code'],
                                        $search
                                    )))
                                    ->mapWithKeys(fn ($p) => [$p['postal_code'] => $p['postal_code'].' — '.$p['place_name']])
                                    ->toArray();
                                if (empty($results) && $search) {
                                    return [$search => $search];
                                }

                                return $results;
                            })
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        Textarea::make('address')
                            ->label(__('Detail Alamat'))
                            ->required()
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // ── Data KYC ────────────────────────────────────────────────
                Section::make(__('Data KYC'))
                    ->icon('heroicon-o-user-group')
                    ->description(__('Lengkapi data berikut sesuai dokumen. Dipakai verifikasi identitas.'))
                    ->schema([
                        Select::make('gender')
                            ->label(__('Jenis Kelamin'))
                            ->required()
                            ->options([
                                'Pria' => __('Pria'),
                                'Wanita' => __('Wanita'),
                            ])
                            ->native(false),
                        Select::make('religion')
                            ->label(__('Agama'))
                            ->options([
                                'Islam' => __('Islam'),
                                'Kristen' => __('Kristen'),
                                'Katolik' => __('Katolik'),
                                'Hindu' => __('Hindu'),
                                'Buddha' => __('Buddha'),
                                'Konghucu' => __('Konghucu'),
                            ])
                            ->native(false)
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Agama'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name']),
                        Select::make('marital_status')
                            ->label(__('Status Pernikahan'))
                            ->options([
                                'Belum Menikah' => __('Belum Menikah'),
                                'Menikah' => __('Menikah'),
                                'Cerai' => __('Cerai'),
                            ])
                            ->native(false),
                        TextInput::make('mother_name')
                            ->label(__('Nama Ibu Kandung'))
                            ->maxLength(255),
                        Select::make('occupation')
                            ->label(__('Pekerjaan'))
                            ->required()
                            ->options([
                                'Karyawan' => __('Karyawan'),
                                'Wiraswasta' => __('Wiraswasta'),
                                'Pelajar/Mahasiswa' => __('Pelajar/Mahasiswa'),
                                'Ibu Rumah Tangga' => __('Ibu Rumah Tangga'),
                                'Profesional' => __('Profesional'),
                                'Lainnya' => __('Lainnya'),
                            ])
                            ->native(false)
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Pekerjaan'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name']),
                        Select::make('income_range')
                            ->label(__('Rentang Penghasilan'))
                            ->options([
                                '< Rp 1 Juta' => __('< Rp 1 Juta'),
                                'Rp 1-5 Juta' => __('Rp 1-5 Juta'),
                                'Rp 5-10 Juta' => __('Rp 5-10 Juta'),
                                'Rp 10-50 Juta' => __('Rp 10-50 Juta'),
                                '> Rp 50 Juta' => __('> Rp 50 Juta'),
                            ])
                            ->native(false),
                        Select::make('source_of_funds')
                            ->label(__('Sumber Dana'))
                            ->options([
                                'Gaji' => __('Gaji'),
                                'Bisnis/Usaha' => __('Bisnis/Usaha'),
                                'Investasi' => __('Investasi'),
                                'Hadiah/Warisan' => __('Hadiah/Warisan'),
                                'Lainnya' => __('Lainnya'),
                            ])
                            ->native(false)
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Sumber Dana'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name']),
                    ])
                    ->columns(1),

                // ── Verifikasi Wajah ────────────────────────────────────────
                Section::make(__('Verifikasi Wajah'))
                    ->icon('heroicon-o-face-smile')
                    ->description(__('Scan wajah Anda untuk verifikasi identitas.')
                        .' '.__('Minimal unggah foto KTP/dokumen agar wajah dapat dibandingkan.'))
                    ->schema([
                        FileUpload::make('face_scan_photo')
                            ->label(__('Scan Wajah'))
                            ->image()
                            ->maxSize(5120)
                            ->directory('face-scans')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->extraInputAttributes(['accept' => 'image/*'])
                            ->extraFieldWrapperAttributes(['class' => 'face-scan-wrapper'])
                            ->helperText(__('Buka kamera atau unggah foto wajah Anda.'))
                            ->columnSpanFull(),
                        View::make('User.social-buttons.agreement-checkboxes')
                            ->columnSpanFull(),
                        View::make('User.social-buttons.auth-buttons')
                            ->viewData(['authMode' => 'register'])
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Hidden::make('agreement'),
                Hidden::make('remember'),
                Hidden::make('full_name')->dehydrated(true),
                Hidden::make('password_strength')->dehydrated(false),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction()
                ->label(__('Daftar')),
        ];
    }

    protected function joinFullName(Get $get): string
    {
        return trim(implode(' ', array_filter([
            $get('first_name'),
            $get('mid_name'),
            $get('last_name'),
        ])));
    }

    /**
     * Skor kekuatan kata sandi 1..5 (indikator, bukan validasi).
     */
    protected function passwordStrength(?string $password): int
    {
        if (blank($password)) {
            return 0;
        }

        return collect([
            strlen($password) >= 12,
            preg_match('/[A-Z]/', $password) === 1,
            preg_match('/[a-z]/', $password) === 1,
            preg_match('/[0-9]/', $password) === 1,
            preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password) === 1,
        ])->filter()->count();
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['full_name'] = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['mid_name'] ?? null,
            $data['last_name'] ?? null,
        ])));

        // Parse "Kota, DD/MM/YYYY" → birth_place + birth_date
        if (! empty($data['birth_place_date'])) {
            $parts               = array_map('trim', explode(',', $data['birth_place_date'], 2));
            $data['birth_place'] = $parts[0] ?? null;
            if (! empty($parts[1])) {
                try {
                    $data['birth_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $parts[1])->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $data['birth_date'] = \Carbon\Carbon::parse(str_replace('/', '-', $parts[1]))->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $data['birth_date'] = null;
                    }
                }
            }
            unset($data['birth_place_date']);
        }

        return $data;

    }

    protected function handleRegistration(array $data): User
    {
        if (! ($data['agreement'] ?? false)) {
            Notification::make()
                ->title(__('Perhatian'))
                ->body(__('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.'))
                ->warning()
                ->send();
            throw ValidationException::withMessages([
                'data.agreement' => __('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.'),
            ]);
        }

        if (! ($data['remember'] ?? false)) {
            Notification::make()
                ->title(__('Perhatian'))
                ->body(__('Anda harus mencentang Ingat Saya untuk melanjutkan.'))
                ->warning()
                ->send();
            throw ValidationException::withMessages([
                'data.remember' => __('Anda harus mencentang Ingat Saya untuk melanjutkan.'),
            ]);
        }

        $ip = request()->ip();

        $user = User::create([
            'avatar_url' => $data['avatar_url'] ?? null,
            'full_name' => $data['full_name'],
            'first_name' => $data['first_name'] ?? null,
            'mid_name' => $data['mid_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? ''),
            'whatsapp' => $data['whatsapp'] ?? null,
            'ktp_number' => $data['ktp_number'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'sim_number' => $data['sim_number'] ?? null,
            'npwp_number' => $data['npwp_number'] ?? null,
            'identity_type' => $data['identity_type'] ?? 'ktp',
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'country' => $data['country'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'village_id' => $data['village_id'] ?? null,
            'province_name' => $data['province_name'] ?? ($data['country'] === 'Indonesia' ? IndonesiaProvince::find($data['province_id'])?->name : null),
            'city_name' => $data['city_name'] ?? ($data['country'] === 'Indonesia' ? IndonesiaCity::find($data['city_id'])?->name : null),
            'district_name' => $data['district_name'] ?? ($data['country'] === 'Indonesia' ? IndonesiaDistrict::find($data['district_id'])?->name : null),
            'village_name' => $data['village_name'] ?? ($data['country'] === 'Indonesia' ? IndonesiaVillage::find($data['village_id'])?->name : null),
            'postal_code' => $data['postal_code'] ?? $data['postal_code_international'] ?? null,
            'ktp_photo' => $data['ktp_photo'] ?? null,
            'selfie_photo' => $data['selfie_photo'] ?? null,
            'face_scan_photo' => $data['face_scan_photo'] ?? null,
            'gender' => $data['gender'] ?? null,
            'religion' => $data['religion'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'income_range' => $data['income_range'] ?? null,
            'source_of_funds' => $data['source_of_funds'] ?? null,
            'address' => $data['address'] ?? null,
            'ip_address' => $ip,
        ]);

        if (! empty($data['selfie_photo']) || ! empty($data['face_scan_photo'])) {
            $user->forceFill(['identity_verified_at' => now()])->save();
        }

        if (! empty($data['face_scan_photo'])) {
            $user->forceFill(['liveness_completed' => true])->save();
        }

        $customerRole = Role::where('name', 'customer')->first(['*']);
        if ($customerRole) {
            $user->assignRole($customerRole);
        }

        $location = app(GeoLocationService::class)->lookup($ip);
        $locationParts = array_filter([
            $location['city'] ?? null,
            $location['region'] ?? null,
            $location['country'] ?? null,
        ]);
        $locationText = $locationParts
            ? implode(', ', $locationParts)
            : __('Lokasi tidak diketahui');

        PlatformNotificationService::send(
            $user,
            __('Pendaftaran Berhasil'),
            __('Akun Anda telah terdaftar dari :ip (:location) pada :time.', [
                'ip' => $ip,
                'location' => $locationText,
                'time' => now()->format('d M Y H:i:s'),
            ])
        );

        Notification::make()
            ->title(__('Pendaftaran Berhasil'))
            ->body(__('Akun Anda Telah Terdaftar :ip (:location) pada :time.', [
                'ip' => $ip,
                'location' => $locationText,
                'time' => now()->format('d M Y H:i:s'),
            ]))
            ->success()
            ->send();

        Notification::make()
            ->title(__('Perhatian'))
            ->body(__('Account Anda Sudah Terdaftar Silahkan Ke Halaman Login.'))
            ->warning()
            ->send();

        return $user;
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->label('')
            ->hidden();
    }
}
