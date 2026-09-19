<?php

namespace App\Filament\Admin\Resources\UserResource;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Laravolt\Indonesia\Models\City as IndonesiaCity;
use Laravolt\Indonesia\Models\District as IndonesiaDistrict;
use Laravolt\Indonesia\Models\Province as IndonesiaProvince;
use Laravolt\Indonesia\Models\Village as IndonesiaVillage;

/**
 * @mixin \Eloquent
 *
 * @property-read User $record
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Pengguna');
    }

    public static function getModelLabel(): string
    {
        return __('Pengguna');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pengguna');
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Builder $query */
        $query = static::$model::query();

        return (string) $query->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Total Pengguna Terdaftar');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Data Pribadi'))
                            ->description(__('Informasi profil detail pengguna.'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\FileUpload::make('avatar_url')
                                    ->label(__('Avatar Profil'))
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->columnSpanFull()
                                    ->alignCenter(),
                                Forms\Components\TextInput::make('full_name')
                                    ->label(__('Nama Lengkap'))
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-user-circle')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (blank($state)) {
                                            return;
                                        }
                                        $parts = explode(' ', trim($state));
                                        $firstName = array_shift($parts);
                                        $lastName = count($parts) > 0 ? array_pop($parts) : '';
                                        $midName = count($parts) > 0 ? implode(' ', $parts) : '';
                                        $set('first_name', $firstName);
                                        $set('mid_name', $midName);
                                        $set('last_name', $lastName);
                                    })
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('first_name')
                                    ->label(__('Nama Depan'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('mid_name')
                                    ->label(__('Nama Tengah'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('last_name')
                                    ->label(__('Nama Belakang'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('gender')
                                    ->label(__('Jenis Kelamin'))
                                    ->options([
                                        'Pria' => __('Pria'),
                                        'Wanita' => __('Wanita'),
                                    ])
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-variable'),
                                Forms\Components\Select::make('religion')
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
                                    ->prefixIcon('heroicon-o-variable'),
                                Forms\Components\Select::make('marital_status')
                                    ->label(__('Status Pernikahan'))
                                    ->options([
                                        'Belum Menikah' => __('Belum Menikah'),
                                        'Menikah' => __('Menikah'),
                                        'Cerai' => __('Cerai'),
                                    ])
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-heart'),
                                Forms\Components\TextInput::make('mother_name')
                                    ->label(__('Nama Ibu Kandung'))
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-user'),
                                Forms\Components\Select::make('occupation')
                                    ->label(__('Pekerjaan'))
                                    ->options([
                                        'Karyawan' => __('Karyawan'),
                                        'Wiraswasta' => __('Wiraswasta'),
                                        'Pelajar/Mahasiswa' => __('Pelajar/Mahasiswa'),
                                        'Ibu Rumah Tangga' => __('Ibu Rumah Tangga'),
                                        'Profesional' => __('Profesional'),
                                        'Lainnya' => __('Lainnya'),
                                    ])
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-briefcase'),
                                Forms\Components\Select::make('income_range')
                                    ->label(__('Rentang Penghasilan'))
                                    ->options([
                                        '< Rp 1 Juta' => __('< Rp 1 Juta'),
                                        'Rp 1-5 Juta' => __('Rp 1-5 Juta'),
                                        'Rp 5-10 Juta' => __('Rp 5-10 Juta'),
                                        'Rp 10-50 Juta' => __('Rp 10-50 Juta'),
                                        '> Rp 50 Juta' => __('> Rp 50 Juta'),
                                    ])
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-currency-dollar'),
                                Forms\Components\Select::make('source_of_funds')
                                    ->label(__('Sumber Dana'))
                                    ->options([
                                        'Gaji' => __('Gaji'),
                                        'Bisnis/Usaha' => __('Bisnis/Usaha'),
                                        'Investasi' => __('Investasi'),
                                        'Hadiah/Warisan' => __('Hadiah/Warisan'),
                                        'Lainnya' => __('Lainnya'),
                                    ])
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-banknotes'),
                                Forms\Components\TextInput::make('whatsapp')
                                    ->label(__('Nomor WhatsApp'))
                                    ->tel()
                                    ->prefixIcon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->maxLength(255)
                                    ->helperText(__('Untuk notifikasi pembayaran. Format: 08xxx atau 628xxx'))
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('identity_type')
                                    ->label(__('Jenis Identitas'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->options([
                                        'ktp' => __('Kartu Tanda Kependudukan (KTP)'),
                                        'passport' => __('Passport'),
                                        'sim' => __('Surat Izin Mengemudi (SIM)'),
                                        'npwp' => __('Nomor Pokok Wajib Pajak (NPWP)'),
                                    ])
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('ktp_number')
                                    ->label(__('Nomor KTP'))
                                    ->visible(fn (Get $get) => $get('identity_type') === 'ktp')
                                    ->maxLength(20)
                                    ->rules(fn (Get $get, ?string $operation): array => $operation === 'edit'
                                        ? ['nullable', 'string', 'size:16', Rule::unique('users')->ignore($get('id'))]
                                        : ['nullable', 'string', 'size:16', Rule::unique('users')])
                                    ->prefixIcon('heroicon-o-identification')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('passport_number')
                                    ->label(__('Nomer Passport'))
                                    ->visible(fn (Get $get) => $get('identity_type') === 'passport')
                                    ->maxLength(20)
                                    ->rules(fn (Get $get, ?string $operation): array => $operation === 'edit'
                                        ? ['nullable', 'string', 'min:6', Rule::unique('users')->ignore($get('id'))]
                                        : ['nullable', 'string', 'min:6', Rule::unique('users')])
                                    ->prefixIcon('heroicon-o-identification')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('sim_number')
                                    ->label(__('Nomor SIM'))
                                    ->visible(fn (Get $get) => $get('identity_type') === 'sim')
                                    ->maxLength(20)
                                    ->rules(fn (Get $get, ?string $operation): array => $operation === 'edit'
                                        ? ['nullable', 'string', 'min:6', 'max:20', Rule::unique('users')->ignore($get('id'))]
                                        : ['nullable', 'string', 'min:6', 'max:20', Rule::unique('users')])
                                    ->prefixIcon('heroicon-o-identification')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('npwp_number')
                                    ->label(__('Nomor NPWP'))
                                    ->visible(fn (Get $get) => $get('identity_type') === 'npwp')
                                    ->maxLength(20)
                                    ->rules(fn (Get $get, ?string $operation): array => $operation === 'edit'
                                        ? ['nullable', 'string', 'min:15', 'max:20', Rule::unique('users')->ignore($get('id'))]
                                        : ['nullable', 'string', 'min:15', 'max:20', Rule::unique('users')])
                                    ->prefixIcon('heroicon-o-identification')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('birth_place')
                                    ->label(__('Tempat Lahir'))
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-map-pin')
                                    ->columnSpan(1),
                                Forms\Components\DatePicker::make('birth_date')
                                    ->label(__('Tanggal Lahir'))
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('country')
                                    ->label(__('Negara'))
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-globe-alt')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('province_id')
                                    ->label(__('Provinsi'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                                    ->options(fn () => IndonesiaProvince::pluck('name', 'id'))
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('city_id', null);
                                        $set('district_id', null);
                                        $set('village_id', null);
                                        $set('province_name', null);
                                        $set('city_name', null);
                                        $set('district_name', null);
                                        $set('village_name', null);
                                    })
                                    ->columnSpan(1),
                                Forms\Components\Select::make('city_id')
                                    ->label(__('Kota/Kabupaten'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                                    ->options(fn (Get $get) => $get('province_id')
                                        ? IndonesiaCity::where('province_code', IndonesiaProvince::find($get('province_id'))?->code)
                                            ->pluck('name', 'id')
                                        : [])
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('district_id', null);
                                        $set('village_id', null);
                                        $set('city_name', null);
                                        $set('district_name', null);
                                        $set('village_name', null);
                                    })
                                    ->columnSpan(1),
                                Forms\Components\Select::make('district_id')
                                    ->label(__('Kecamatan'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                                    ->options(fn (Get $get) => $get('city_id')
                                        ? IndonesiaDistrict::where('city_code', IndonesiaCity::find($get('city_id'))?->code)
                                            ->pluck('name', 'id')
                                        : [])
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('village_id', null);
                                        $set('district_name', null);
                                        $set('village_name', null);
                                    })
                                    ->columnSpan(1),
                                Forms\Components\Select::make('village_id')
                                    ->label(__('Kelurahan/Desa'))
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                                    ->options(fn (Get $get) => $get('district_id')
                                        ? IndonesiaVillage::where('district_code', IndonesiaDistrict::find($get('district_id'))?->code)
                                            ->pluck('name', 'id')
                                        : [])
                                    ->columnSpan(1),
                                Forms\Components\Select::make('province_name')
                                    ->label(__('Provinsi / State'))
                                    ->searchable()
                                    ->native(false)
                                    ->getSearchResultsUsing(fn (string $search) => $search ? [$search => $search] : [])
                                    ->getOptionLabelUsing(fn ($value) => $value)
                                    ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('city_name')
                                    ->label(__('Kota / City'))
                                    ->searchable()
                                    ->native(false)
                                    ->getSearchResultsUsing(fn (string $search) => $search ? [$search => $search] : [])
                                    ->getOptionLabelUsing(fn ($value) => $value)
                                    ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('district_name')
                                    ->label(__('Kecamatan / District'))
                                    ->searchable()
                                    ->native(false)
                                    ->getSearchResultsUsing(fn (string $search) => $search ? [$search => $search] : [])
                                    ->getOptionLabelUsing(fn ($value) => $value)
                                    ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('village_name')
                                    ->label(__('Kelurahan / Village'))
                                    ->searchable()
                                    ->native(false)
                                    ->getSearchResultsUsing(fn (string $search) => $search ? [$search => $search] : [])
                                    ->getOptionLabelUsing(fn ($value) => $value)
                                    ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('postal_code')
                                    ->label(__('Kode Pos / Postal Code'))
                                    ->maxLength(10)
                                    ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('postal_code')
                                    ->label(__('Kode Pos / Postal Code'))
                                    ->searchable()
                                    ->native(false)
                                    ->getSearchResultsUsing(fn (string $search) => $search ? [$search => $search] : [])
                                    ->getOptionLabelUsing(fn ($value) => $value)
                                    ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia')
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('address')
                                    ->label(__('Alamat Tempat Tinggal'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('ip_address')
                                    ->label(__('Alamat IP'))
                                    ->disabled()
                                    ->maxLength(45)
                                    ->helperText(__('IP terakhir yang tercatat untuk pengguna.'))
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('login_city')
                                    ->label(__('Kota'))
                                    ->disabled()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('login_region')
                                    ->label(__('Provinsi'))
                                    ->disabled()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('login_country')
                                    ->label(__('Negara'))
                                    ->disabled()
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Akses & Akun'))
                            ->description(__('Manajemen login, keamanan, dan perizinan.'))
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\TextInput::make('username')
                                    ->label(__('Username'))
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-at-symbol'),
                                Forms\Components\TextInput::make('email')
                                    ->label(__('Alamat Email'))
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-envelope'),
                                Forms\Components\TextInput::make('password')
                                    ->label(__('Kata Sandi'))
                                    ->password()
                                    ->rule(Password::min(12)
                                        ->letters()
                                        ->mixedCase()
                                        ->numbers()
                                        ->symbols()
                                        ->uncompromised()
                                    )
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->maxLength(255)
                                    ->revealable()
                                    ->prefixIcon('heroicon-o-key'),
                                Forms\Components\DateTimePicker::make('email_verified_at')
                                    ->label(__('Waktu Verifikasi Email'))
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-check-badge'),
                            ]),

                        Forms\Components\Section::make(__('Koneksi Sosial'))
                            ->description(__('Informasi akun yang terhubung melalui pihak ketiga.'))
                            ->icon('heroicon-o-link')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('social_type')
                                            ->label(__('Metode Login'))
                                            ->placeholder(__('Manual'))
                                            ->formatStateUsing(fn ($state) => $state === 'google' ? 'Google' : $state)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->prefixIcon('heroicon-o-globe-alt'),
                                        Forms\Components\TextInput::make('social_id')
                                            ->label(__('ID Akun Google'))
                                            ->placeholder(__('N/A'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->prefixIcon('heroicon-o-identification'),
                                    ]),
                            ])
                            ->collapsible()
                            ->collapsed(fn (?User $record) => blank($record?->social_id)),

                        Forms\Components\Section::make(__('Verifikasi Wajah (KYC)'))
                            ->description(__('Verifikasi identitas pengguna: KTP, selfie, dan face scan untuk keperluan KYC. Berbeda dari Kunci Aplikasi Wajah yang disimpan terpisah.'))
                            ->icon('heroicon-o-face-smile')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\FileUpload::make('ktp_photo')
                                            ->label(fn (Get $get) => match ($get('identity_type')) {
                                                'ktp' => __('Foto KTP'),
                                                'passport' => __('Foto Passport'),
                                                'sim' => __('Foto SIM'),
                                                'npwp' => __('Foto NPWP'),
                                                default => __('Foto Identitas'),
                                            })
                                            ->image()
                                            ->maxSize(5120)
                                            ->directory('ktp-photos'),
                                        Forms\Components\FileUpload::make('selfie_photo')
                                            ->label(__('Foto Selfie'))
                                            ->image()
                                            ->maxSize(5120)
                                            ->directory('selfies'),
                                        Forms\Components\FileUpload::make('face_scan_photo')
                                            ->label(__('Foto Face Scan'))
                                            ->image()
                                            ->maxSize(5120)
                                            ->directory('face-scans'),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('face_similarity')
                                            ->label(__('Skor Kemiripan AI'))
                                            ->disabled()
                                            ->suffix('%')
                                            ->placeholder('-')
                                            ->helperText(__('Kombinasi cosine FaceNet + ORB (0-100).'))
                                            ->prefixIcon('heroicon-o-chart-bar'),
                                        Forms\Components\TextInput::make('face_reason')
                                            ->label(__('Alasan Hasil AI'))
                                            ->disabled()
                                            ->placeholder('-')
                                            ->prefixIcon('heroicon-o-document-text'),
                                        Forms\Components\Toggle::make('liveness_completed')
                                            ->label(__('Liveness di Perangkat Selesai'))
                                            ->disabled()
                                            ->helperText(__('Instruksi gerakan kepala & mata terbuka di aplikasi.')),
                                        Forms\Components\Placeholder::make('face_liveness')
                                            ->label(__('Detail Liveness Server'))
                                            ->content(fn (?User $record) => self::formatLiveness($record?->face_liveness)),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('kyc_status')
                                            ->label(__('Status Review KYC'))
                                            ->options([
                                                'verified' => __('Disetujui'),
                                                'rejected' => __('Ditolak'),
                                            ])
                                            ->placeholder(__('Menunggu Review'))
                                            ->helperText(__('Kosongkan untuk mengembalikan ke status menunggu review.'))
                                            ->native(false)
                                            ->prefixIcon('heroicon-o-shield-check'),
                                        Forms\Components\Placeholder::make('kyc_reviewer')
                                            ->label(__('Direview Oleh'))
                                            ->content(fn (?User $record) => $record?->kycReviewer?->full_name ?? '-'),
                                        Forms\Components\DateTimePicker::make('kyc_reviewed_at')
                                            ->label(__('Waktu Review'))
                                            ->disabled()
                                            ->native(false)
                                            ->prefixIcon('heroicon-o-clock'),
                                        Forms\Components\DateTimePicker::make('identity_verified_at')
                                            ->label(__('Waktu Verifikasi Identitas (AI)'))
                                            ->disabled()
                                            ->native(false)
                                            ->prefixIcon('heroicon-o-check-badge'),
                                        Forms\Components\Textarea::make('kyc_notes')
                                            ->label(__('Catatan Admin'))
                                            ->rows(3)
                                            ->placeholder(__('Alasan approve/reject, tercatat untuk audit.'))
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->collapsible()
                            ->collapsed(),

                        Forms\Components\Section::make(__('Otorisasi'))
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Select::make('roles')
                                    ->searchable()
                                    ->label(__('Peran Sistem (Role)'))
                                    ->relationship('roles', 'name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->headline())
                                    ->multiple()
                                    ->preload(),
                                Forms\Components\Toggle::make('active_status')
                                    ->label(__('Status Akun Aktif'))
                                    ->required()
                                    ->disabled(fn (?User $record) => $record?->hasRole('super_admin') ?? false)
                                    ->helperText(__('Super admin tidak dapat dinonaktifkan demi alasan keamanan.'))
                                    ->onIcon('heroicon-s-check')
                                    ->offIcon('heroicon-s-x-mark'),
                            ]),

                        Forms\Components\Section::make(__('Kunci Aplikasi'))
                            ->description(__('Pengaturan kunci aplikasi per pengguna: sidik jari perangkat, wajah terdaftar (AI Core), dan PIN lokal.'))
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('app_lock_fingerprint_enabled')
                                            ->label(__('Sidik Jari (Perangkat)'))
                                            ->disabled()
                                            ->helperText(__('Aktif jika pengguna mengaktifkan kunci sidik jari di perangkatnya.')),
                                        Forms\Components\Toggle::make('app_lock_face_enabled')
                                            ->label(__('Wajah (AI Core — Kunci Aplikasi)'))
                                            ->disabled()
                                            ->helperText(__('Aktif jika pengguna mendaftarkan wajah untuk membuka kunci aplikasi.')),
                                        Forms\Components\Toggle::make('app_lock_pin_enabled')
                                            ->label(__('PIN Lokal'))
                                            ->disabled()
                                            ->helperText(__('Aktif jika pengguna mengatur PIN 6 digit untuk kunci aplikasi.')),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('app_lock_face_enrolled')
                                            ->label(__('Wajah Terdaftar (Kunci Aplikasi)'))
                                            ->disabled()
                                            ->helperText(__('Wajah sudah didaftarkan dan diverifikasi. Berbeda dari Verifikasi Wajah KYC.')),
                                        Forms\Components\Placeholder::make('app_lock_face_reference_display')
                                            ->label(__('Foto Wajah Terdaftar (Kunci Aplikasi)'))
                                            ->content(fn (?User $record) => $record?->app_lock_face_reference
                                                ? '<img src="'.Storage::disk('public')->url($record->app_lock_face_reference).'" style="max-height:120px;border-radius:8px;" />'
                                                : '-'),
                                    ]),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('app_lock_face_enrolled_at')
                                            ->label(__('Wajah Didaftarkan'))
                                            ->disabled()
                                            ->placeholder('-'),
                                        Forms\Components\TextInput::make('app_lock_last_unlock_at')
                                            ->label(__('Terakhir Dibuka'))
                                            ->disabled()
                                            ->placeholder('-'),
                                    ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label(__('Avatar'))
                    ->circular()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->label(__('Nama Lengkap')),

                Tables\Columns\TextColumn::make('gender')
                    ->label(__('Jenis Kelamin'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Pria' => 'info',
                        'Wanita' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'Pria' => __('Pria'),
                        'Wanita' => __('Wanita'),
                        default => $state ?? '-',
                    })
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('religion')
                    ->label(__('Agama'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('marital_status')
                    ->label(__('Status Pernikahan'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('mother_name')
                    ->label(__('Nama Ibu Kandung'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('occupation')
                    ->label(__('Pekerjaan'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('income_range')
                    ->label(__('Rentang Penghasilan'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('source_of_funds')
                    ->label(__('Sumber Dana'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('Nama Depan')),

                Tables\Columns\TextColumn::make('mid_name')
                    ->label(__('Nama Tengah')),

                Tables\Columns\TextColumn::make('last_name')
                    ->label(__('Nama Belakang')),

                Tables\Columns\TextColumn::make('identity_type')
                    ->label(__('Identitas'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ktp' => 'primary',
                        'passport' => 'warning',
                        'sim' => 'info',
                        'npwp' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ktp' => __('Kartu Tanda Kependudukan (KTP)'),
                        'passport' => __('Passport'),
                        'sim' => __('SIM'),
                        'npwp' => __('NPWP'),
                        default => '-',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ktp_number')
                    ->label(__('Nomor KTP'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('passport_number')
                    ->label(__('Passport'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('sim_number')
                    ->label(__('SIM'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('npwp_number')
                    ->label(__('NPWP'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->label(__('Username')),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->label(__('Email')),

                Tables\Columns\TextColumn::make('birth_place')
                    ->label(__('Tempat Lahir'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('birth_date')
                    ->label(__('Tanggal Lahir'))
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('country')
                    ->label(__('Negara'))
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('province_name')
                    ->label(__('Provinsi / State'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->description(fn ($record) => $record->province?->name),

                Tables\Columns\TextColumn::make('city_name')
                    ->label(__('Kota / City'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->description(fn ($record) => $record->city?->name),

                Tables\Columns\TextColumn::make('district_name')
                    ->label(__('Kecamatan / District'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->description(fn ($record) => $record->district?->name),

                Tables\Columns\TextColumn::make('village_name')
                    ->label(__('Kelurahan / Village'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->description(fn ($record) => $record->village?->name),

                Tables\Columns\TextColumn::make('postal_code')
                    ->label(__('Kode Pos / Postal Code'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->searchable()
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->iconColor('success')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\ImageColumn::make('selfie_photo')
                    ->label(__('Selfie'))
                    ->circular()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(40),

                Tables\Columns\TextColumn::make('identity_verified_at')
                    ->label(__('Waktu Verifikasi'))
                    ->dateTime()
                    ->alignment('center')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? __('Terverifikasi') : __('Belum'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ImageColumn::make('face_scan_photo')
                    ->label(__('Face Scan'))
                    ->circular()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(40),

                Tables\Columns\TextColumn::make('kyc_status')
                    ->label(__('Status KYC'))
                    ->badge()
                    ->alignment('center')
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'verified' => __('Disetujui'),
                        'rejected' => __('Ditolak'),
                        default => __('Menunggu Review'),
                    })
                    ->description(fn (User $record): string => match ($record->kyc_status) {
                        'verified' => $record->kycReviewer?->full_name ?? __('AI'),
                        'rejected' => $record->kyc_notes ?? '',
                        default => $record->ktp_photo || $record->selfie_photo || $record->face_scan_photo
                            ? __('Ada dokumen, belum direview')
                            : __('Belum ada dokumen'),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('face_similarity')
                    ->label(__('Kemiripan AI'))
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' %')
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-')
                    ->color(fn ($state): string => $state !== null && (float) $state >= 55 ? 'success' : 'danger'),

                Tables\Columns\IconColumn::make('liveness_completed')
                    ->label(__('Liveness'))
                    ->boolean()
                    ->alignment('center')
                    ->tooltip(__('Liveness di perangkat selesai'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label(__('Diverifikasi Pada'))
                    ->dateTime()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('social_type')
                    ->label(__('Metode Login'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'google' ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'google' ? 'Google' : $state)
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->searchable()
                    ->label(__('Peran'))
                    ->badge()
                    ->alignment('center')
                    ->formatStateUsing(fn ($state): string => __((string) str($state)->headline())),

                Tables\Columns\ToggleColumn::make('active_status')
                    ->label(__('Status Aktif'))
                    ->disabled(fn (?User $record) => $record?->hasRole('super_admin') ?? false)
                    ->alignment('center')
                    ->afterStateUpdated(function ($record, $state): void {
                        if (! $state) {
                            $record->tokens()->delete();
                        }
                    }),

                Tables\Columns\TextColumn::make('app_lock')
                    ->label(__('Kunci Aplikasi'))
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function (?User $record): array {
                        $badges = [];
                        if ($record->app_lock_fingerprint_enabled) $badges[] = 'Fingerprint';
                        if ($record->app_lock_face_enabled) $badges[] = 'Face ID';
                        if ($record->app_lock_pin_enabled) $badges[] = 'PIN';
                        return $badges;
                    })
                    ->color(fn (?User $record): string => match (true) {
                        $record->app_lock_fingerprint_enabled || $record->app_lock_face_enabled || $record->app_lock_pin_enabled => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Terdaftar Pada'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Terakhir Diperbarui'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignment('center'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kyc_status')
                    ->label(__('Status KYC'))
                    ->options([
                        'pending' => __('Menunggu Review'),
                        'verified' => __('Disetujui'),
                        'rejected' => __('Ditolak'),
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if ($value === 'pending') {
                            $query
                                ->whereNull('kyc_status')
                                ->where(fn (Builder $q) => $q
                                    ->whereNotNull('ktp_photo')
                                    ->orWhereNotNull('selfie_photo')
                                    ->orWhereNotNull('face_scan_photo'));
                        } elseif ($value === 'verified') {
                            $query->where('kyc_status', 'verified');
                        } elseif ($value === 'rejected') {
                            $query->where('kyc_status', 'rejected');
                        }
                    }),
                Tables\Filters\TernaryFilter::make('identity_verified_at')
                    ->label(__('Terverifikasi AI'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('identity_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('identity_verified_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('approveKyc')
                    ->label(__('Setujui KYC'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Setujui Verifikasi Identitas'))
                    ->modalDescription(__('Setujui hasil verifikasi identitas pengguna ini. Status KYC akan menjadi "Disetujui".'))
                    ->visible(fn (User $record): bool => $record->kyc_status !== 'verified' && ($record->ktp_photo || $record->selfie_photo || $record->face_scan_photo))
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'kyc_status' => 'verified',
                            'kyc_reviewed_by' => auth()->id(),
                            'kyc_reviewed_at' => now(),
                            'kyc_notes' => null,
                            'identity_verified_at' => $record->identity_verified_at ?? now(),
                        ])->save();
                        Notification::make()
                            ->success()
                            ->title(__('KYC disetujui'))
                            ->body(__('Verifikasi identitas pengguna ini telah disetujui.'))
                            ->send();
                    }),
                Tables\Actions\Action::make('rejectKyc')
                    ->label(__('Tolak KYC'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Tolak Verifikasi Identitas'))
                    ->modalDescription(__('Tolak verifikasi identitas pengguna ini. Status verifikasi akan dicabut.'))
                    ->form([
                        Forms\Components\Textarea::make('kyc_notes')
                            ->label(__('Alasan Penolakan'))
                            ->required()
                            ->rows(3)
                            ->placeholder(__('Contoh: foto tidak sesuai, wajah tidak terbaca, dokumen mencurigakan')),
                    ])
                    ->modalSubmitActionLabel(__('Tolak KYC'))
                    ->visible(fn (User $record): bool => $record->kyc_status !== 'rejected' && ($record->ktp_photo || $record->selfie_photo || $record->face_scan_photo))
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'kyc_status' => 'rejected',
                            'kyc_reviewed_by' => auth()->id(),
                            'kyc_reviewed_at' => now(),
                            'kyc_notes' => $data['kyc_notes'] ?? null,
                            'identity_verified_at' => null,
                        ])->save();
                        Notification::make()
                            ->danger()
                            ->title(__('KYC ditolak'))
                            ->body(__('Verifikasi identitas pengguna ini ditolak.'))
                            ->send();
                    }),
                Tables\Actions\Action::make('resetAppLock')
                    ->label(__('Reset Kunci Aplikasi'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Reset Kunci Aplikasi'))
                    ->modalDescription(__('Nonaktifkan semua metode kunci aplikasi (sidik jari, Face ID, PIN) dan hapus data wajah terdaftar.'))
                    ->visible(fn (User $record): bool => $record->app_lock_fingerprint_enabled || $record->app_lock_face_enabled || $record->app_lock_pin_enabled)
                    ->action(function (User $record): void {
                        $record->update([
                            'app_lock_fingerprint_enabled' => false,
                            'app_lock_face_enabled' => false,
                            'app_lock_pin_enabled' => false,
                            'app_lock_pin_hash' => null,
                            'app_lock_face_enrolled' => false,
                            'app_lock_face_reference' => null,
                            'app_lock_face_enrolled_at' => null,
                            'app_lock_last_unlock_at' => null,
                        ]);
                        Notification::make()
                            ->success()
                            ->title(__('Kunci Aplikasi direset'))
                            ->body(__('Semua metode kunci aplikasi pengguna telah dinonaktifkan.'))
                            ->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->button()
                    ->color('info')
                    ->size('lg'),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Pengguna diperbarui'))
                            ->body(__('Pengguna telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Pengguna dihapus'))
                            ->body(__('Pengguna telah berhasil dihapus.'))
                    ),
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
            'index' => Pages\ManageUsers\ManageUsers::route('/'),
        ];
    }

    /**
     * Format hasil liveness/anti-spoof dari AI Core untuk ditampilkan di panel.
     */
    protected static function formatLiveness(?array $liveness): string
    {
        if (empty($liveness)) {
            return '-';
        }

        $labels = [
            'blur_pass' => __('Bebas blur'),
            'single_face' => __('Wajah tunggal'),
            'face_size_ok' => __('Ukuran wajah cukup'),
            'eyes_open' => __('Mata terbuka'),
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $liveness)) {
                $rows[] = $label.': '.($liveness[$key] ? __('Ya') : __('Tidak'));
            }
        }

        if (isset($liveness['mtcnn_confidence'])) {
            $rows[] = __('Confidence MTCNN').': '.round((float) $liveness['mtcnn_confidence'] * 100, 1).'%';
        }

        return $rows ? implode(' | ', $rows) : '-';
    }
}
