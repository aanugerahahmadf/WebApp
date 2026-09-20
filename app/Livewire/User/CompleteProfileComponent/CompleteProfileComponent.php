<?php

namespace App\Livewire\User\CompleteProfileComponent;

use App\Forms\Components\BirthPlaceDatePicker\BirthPlaceDatePicker;
use App\Forms\Components\CalendarPicker\CalendarPicker;
use App\Models\User\User;
use App\Services\FaceService\FaceService;
use App\Support\Phone\CountryCallingCodeOptions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravolt\Indonesia\Models\City as IndonesiaCity;
use Laravolt\Indonesia\Models\District as IndonesiaDistrict;
use Laravolt\Indonesia\Models\Province as IndonesiaProvince;
use Laravolt\Indonesia\Models\Village as IndonesiaVillage;
use Livewire\Component;

class CompleteProfileComponent extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    /** Path face scan yang sudah diverifikasi AI (untuk mencegah verifikasi ulang). */
    public ?string $faceAiVerifiedPath = null;

    /** Hasil verifikasi wajah AI: [verified, similarity, reason, message]. */
    public ?array $faceAiResult = null;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $whatsapp = CountryCallingCodeOptions::split($user->whatsapp);

            $this->form->fill([
                'avatar_url' => filter_var($user->getRawOriginal('avatar_url'), FILTER_VALIDATE_URL) ? null : $user->getRawOriginal('avatar_url'),
                'full_name' => $user->full_name,
                'first_name' => $user->first_name,
                'mid_name' => $user->mid_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'whatsapp_country_code' => $whatsapp['selection'],
                'whatsapp' => $whatsapp['national'],
                'identity_type' => $user->identity_type ?? 'ktp',
                'ktp_number' => $user->ktp_number,
                'passport_number' => $user->passport_number,
                'sim_number' => $user->sim_number,
                'npwp_number' => $user->npwp_number,
                'birth_place_date' => $user->birth_place
                    ? $user->birth_place . ($user->birth_date ? ', ' . \Carbon\Carbon::parse($user->birth_date)->format('d/m/Y') : '')
                    : null,
                'country' => $user->country ?? 'Indonesia',
                'province_id' => $user->province_id,
                'city_id' => $user->city_id,
                'district_id' => $user->district_id,
                'village_id' => $user->village_id,
                'province_name' => $user->province_name,
                'city_name' => $user->city_name,
                'district_name' => $user->district_name,
                'village_name' => $user->village_name,
                'postal_code' => $user->postal_code,
                'address' => $user->address,
                'gender' => $user->gender,
                'religion' => $user->religion,
                'marital_status' => $user->marital_status,
                'mother_name' => $user->mother_name,
                'occupation' => $user->occupation,
                'income_range' => $user->income_range,
                'source_of_funds' => $user->source_of_funds,
                'ktp_photo' => $user->getRawOriginal('ktp_photo'),
                'selfie_photo' => $user->getRawOriginal('selfie_photo'),
                'face_scan_photo' => $user->getRawOriginal('face_scan_photo'),
            ]);

            if ($user->face_verified_at && $user->getRawOriginal('face_scan_photo')) {
                $this->faceAiVerifiedPath = $user->getRawOriginal('face_scan_photo');
                $this->faceAiResult = [
                    'verified' => true,
                    'similarity' => $user->face_similarity,
                    'reason' => $user->face_reason ?? 'VERIFIED',
                    'message' => __('Wajah Anda sudah terverifikasi sebelumnya.'),
                ];
            }
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                // ── Foto Profil ─────────────────────────────────────────────
                Section::make(__('Foto Profil'))
                    ->icon('heroicon-o-camera')
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label(__(''))
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1'])
                            ->directory('avatars')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->maxSize(5120)
                            ->extraInputAttributes(['accept' => 'image/*'])
                            ->columnSpanFull(),
                    ]),

                // ── Nama Lengkap ────────────────────────────────────────────
                Section::make(__('Nama Lengkap'))
                    ->icon('heroicon-o-user')
                    ->description(__('Nama sesuai dokumen identitas Anda.'))
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('Nama Depan'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $set('full_name', $this->joinFullName($get));
                            }),
                        TextInput::make('mid_name')
                            ->label(__('Nama Tengah'))
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $set('full_name', $this->joinFullName($get));
                            }),
                        TextInput::make('last_name')
                            ->label(__('Nama Belakang'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $set('full_name', $this->joinFullName($get));
                            }),
                        TextInput::make('full_name')
                            ->label(__('Preview Nama Lengkap'))
                            ->readOnly()
                            ->dehydrated(false),
                    ])->columns(3),

                // ── Username ────────────────────────────────────────────────
                Section::make(__('Username'))
                    ->icon('heroicon-o-at-symbol')
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->unique(User::class, 'username', ignorable: Auth::user())
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Alamat Email'))
                            ->email()
                            ->required()
                            ->readOnly()
                            ->helperText(__('Untuk mengubah email, hubungi dukungan.')),
                    ])->columns(2),

                // ── Nomor WhatsApp ──────────────────────────────────────────
                Section::make(__('Nomor WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->description(__('Untuk notifikasi pembayaran via WhatsApp.'))
                    ->schema([
                        Group::make([
                            Select::make('whatsapp_country_code')
                                ->label(__('Negara / Kode Negara'))
                                ->options(CountryCallingCodeOptions::all())
                                ->default(CountryCallingCodeOptions::defaultSelection())
                                ->searchable()
                                ->native(false)
                                ->allowHtml()
                                ->live()
                                ->dehydrated(false)
                                ->columnSpanFull(),
                            TextInput::make('whatsapp')
                                ->label(__('Nomor WhatsApp'))
                                ->tel()
                                ->required()
                                ->prefix(fn (Get $get): string => explode('|', $get('whatsapp_country_code') ?: CountryCallingCodeOptions::defaultSelection())[0])
                                ->placeholder(__('81234567890'))
                                ->dehydrateStateUsing(fn ($state, Get $get): string => CountryCallingCodeOptions::toE164($get('whatsapp_country_code'), $state))
                                ->columnSpanFull(),
                        ])->columns(1)->columnSpanFull(),
                    ]),

                // ── Identitas & Alamat ──────────────────────────────────────
                Section::make(__('Identitas & Alamat'))
                    ->icon('heroicon-o-shield-check')
                    ->description(__('Pilih jenis identitas, scan dokumen, lalu lengkapi alamat.'))
                    ->schema([
                        Select::make('identity_type')
                            ->label(__('Jenis Identitas'))
                            ->required()
                            ->options([
                                'ktp' => __('Kartu Tanda Penduduk (KTP)'),
                                'passport' => __('Passport'),
                                'sim' => __('Surat Izin Mengemudi (SIM)'),
                                'npwp' => __('Nomor Pokok Wajib Pajak (NPWP)'),
                            ])
                            ->native(false)
                            ->live()
                            ->columnSpan(2),
                        TextInput::make('ktp_number')
                            ->label(__('Nomor KTP'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'ktp')
                            ->maxLength(20)
                            ->columnSpan(1),
                        TextInput::make('passport_number')
                            ->label(__('Nomor Passport'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'passport')
                            ->maxLength(20)
                            ->columnSpan(1),
                        TextInput::make('sim_number')
                            ->label(__('Nomor SIM'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'sim')
                            ->maxLength(20)
                            ->columnSpan(1),
                        TextInput::make('npwp_number')
                            ->label(__('Nomor NPWP'))
                            ->visible(fn (Get $get) => $get('identity_type') === 'npwp')
                            ->maxLength(20)
                            ->columnSpan(1),
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
                            ->helperText(__('Scan atau unggah foto dokumen dari kamera/galeri.'))
                            ->columnSpan(1),
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
                            ->columnSpan(1),
                        BirthPlaceDatePicker::make('birth_place_date')
                            ->label(__('Tempat & Tanggal Lahir'))
                            ->required()
                            ->semiboldAll()
                            ->columnSpanFull(),
                        Select::make('country')
                            ->label(__('Negara'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->default('Indonesia')
                            ->live()
                            ->options(config('countries'))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nama Negara'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name'])
                            ->columnSpan(2),
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
                                $set('city_name', null);
                                $set('district_name', null);
                                $set('village_name', null);
                            }),
                        Select::make('city_id')
                            ->label(__('Kota/Kabupaten'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn (Get $get) => $get('province_id')
                                ? IndonesiaCity::where('province_code', IndonesiaProvince::find($get('province_id'))?->code)->pluck('name', 'id')
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
                                ? IndonesiaDistrict::where('city_code', IndonesiaCity::find($get('city_id'))?->code)->pluck('name', 'id')
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
                                $set('village_name', null);
                            }),
                        Select::make('village_id')
                            ->label(__('Kelurahan/Desa'))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get) => $get('country') === 'Indonesia')
                            ->options(fn (Get $get) => $get('district_id')
                                ? IndonesiaVillage::where('district_code', IndonesiaDistrict::find($get('district_id'))?->code)->pluck('name', 'id')
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
                        TextInput::make('province_name')
                            ->label(__('Provinsi / State'))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        TextInput::make('city_name')
                            ->label(__('Kota / City'))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        TextInput::make('district_name')
                            ->label(__('Kecamatan / District'))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        TextInput::make('village_name')
                            ->label(__('Kelurahan / Village'))
                            ->visible(fn (Get $get) => $get('country') !== null && $get('country') !== 'Indonesia'),
                        TextInput::make('postal_code')
                            ->label(__('Kode Pos'))
                            ->maxLength(10),
                        Textarea::make('address')
                            ->label(__('Alamat Lengkap'))
                            ->required()
                            ->rows(3)
                            ->columnSpan(2),
                    ])->columns(2),

                // ── Data KYC ────────────────────────────────────────────────
                Section::make(__('Data KYC'))
                    ->icon('heroicon-o-user-group')
                    ->description(__('Dipakai untuk verifikasi identitas.'))
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
                    ])->columns(3),

                // ── Verifikasi Wajah ────────────────────────────────────────
                Section::make(__('Verifikasi Wajah'))
                    ->icon('heroicon-o-face-smile')
                    ->description(__('Scan wajah Anda untuk verifikasi identitas.'))
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
                    ]),
            ]);
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
     * Jalankan "perintah bot/AI" verifikasi wajah: bandingkan face scan dengan
     * foto KTP user (mirror endpoint mobile POST /profile/face-scan).
     *
     * @return array<string, mixed>
     */
    public function verifyFaceAi(): array
    {
        $user = Auth::user();

        $facePath = $this->data['face_scan_photo'] ?? null;
        $ktpPath = $this->data['ktp_photo'] ?? $user->getRawOriginal('ktp_photo');

        if (! $facePath) {
            return $this->setFaceAiResult(false, null, 'NO_FACE', __('Ambil foto wajah terlebih dahulu.'));
        }

        if (! $ktpPath) {
            return $this->setFaceAiResult(false, null, 'NO_KTP', __('Unggah foto KTP terlebih dahulu untuk verifikasi wajah.'));
        }

        $faceReal = $this->resolvePublicPath($facePath);
        $ktpReal = $this->resolvePublicPath($ktpPath);

        if (! $faceReal || ! file_exists($faceReal) || ! $ktpReal || ! file_exists($ktpReal)) {
            return $this->setFaceAiResult(false, null, 'UNAVAILABLE', __('Berkas foto belum tersimpan. Silakan unggah ulang.'));
        }

        $ai = app(FaceService::class)->verifyFace($faceReal, $ktpReal);

        $verified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

        $update = ['liveness_completed' => true];

        if ($ai['success'] ?? false) {
            $update['kyc_status'] = null;
            $update['face_similarity'] = $ai['similarity'] ?? null;
            $update['face_deep_similarity'] = $ai['deep_similarity'] ?? null;
            $update['face_reason'] = $ai['reason'] ?? null;
            $update['face_liveness'] = $ai['liveness_checks'] ?? null;
            $update['face_verified_at'] = $verified ? now() : null;
            $update['identity_verified_at'] = $verified ? now() : $user->identity_verified_at;
        }

        $user->forceFill($update)->save();

        $this->faceAiVerifiedPath = $verified ? $facePath : null;

        return $this->setFaceAiResult(
            $verified,
            $ai['similarity'] ?? null,
            $ai['reason'] ?? ($ai['message'] ?? 'UNKNOWN'),
            $verified
                ? __('Wajah berhasil diverifikasi: cocok dengan KTP Anda.')
                : ($ai['message'] ?? __('Verifikasi wajah belum berhasil. Silakan coba lagi dengan pencahayaan yang baik.'))
        );
    }

    /**
     * Simpan hasil terakhir verifikasi AI lalu kembalikan untuk UI.
     *
     * @return array<string, mixed>
     */
    protected function setFaceAiResult(bool $verified, ?float $similarity, string $reason, string $message): array
    {
        $this->faceAiResult = compact('verified', 'similarity', 'reason', 'message');

        return $this->faceAiResult;
    }

    /**
     * Resolve path relatif hasil FileUpload (disk public) menjadi path absolut.
     */
    protected function resolvePublicPath(string $path): ?string
    {
        $path = ltrim(str_replace('storage/', '', $path), '/');

        $real = Storage::disk('public')->path($path);

        return file_exists($real) ? $real : null;
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $user = Auth::user();

            if (empty($data['avatar_url'])) {
                unset($data['avatar_url']);
            }

            $faceScan = $data['face_scan_photo'] ?? null;

            // "Perintah bot/AI": wajib verifikasi wajah sebelum simpan bila ada
            // scan wajah yang belum pernah diverifikasi AI untuk file tersebut.
            if ($faceScan && $faceScan !== $this->faceAiVerifiedPath) {
                $this->verifyFaceAi();
            }

            $aiOk = ($this->faceAiResult['verified'] ?? false) === true;

            if ($faceScan && ! $aiOk) {
                $reason = $this->faceAiResult['reason'] ?? 'UNKNOWN';

                $message = $reason === 'NO_KTP'
                    ? __('Unggah foto KTP terlebih dahulu untuk verifikasi wajah.')
                    : ($this->faceAiResult['message'] ?? __('Scan ulang wajah Anda agar cocok dengan foto identitas.'));

                Notification::make()
                    ->title(__('Verifikasi wajah belum berhasil'))
                    ->body($message)
                    ->danger()
                    ->send();

                return;
            }

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

            $user->update($data);

            if (! empty($data['face_scan_photo'])) {
                $user->forceFill(['liveness_completed' => true])->save();
            }

            if (! empty($data['selfie_photo']) || ! empty($data['face_scan_photo'])) {
                $user->forceFill(['identity_verified_at' => now()])->save();
            }

            Notification::make()
                ->title(__('Profil berhasil dilengkapi!'))
                ->success()
                ->send();

            $this->redirectRoute('filament.user.pages.home');
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Gagal menyimpan profil'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        return view('User.livewire.complete-profile-component.complete-profile-component');
    }
}
