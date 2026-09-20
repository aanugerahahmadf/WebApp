<?php

namespace App\Livewire\User\PersonalInfoComponent;

use App\Models\WhatsappOtp\WhatsappOtp;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use App\Support\Phone\CountryCallingCodeOptions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * @mixin Component
 */
class PersonalInfoComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static int $sort = 1;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $rawAvatar = $user->getRawOriginal('avatar_url');
            $whatsapp = CountryCallingCodeOptions::split($user->whatsapp);

            $this->form->fill([
                'avatar_url' => filter_var($rawAvatar, FILTER_VALIDATE_URL) ? null : $rawAvatar,
                'first_name' => $user->first_name,
                'mid_name' => $user->mid_name,
                'last_name' => $user->last_name,
                'full_name' => $this->buildFullName($user->first_name, $user->mid_name, $user->last_name),
                'username' => $user->username,
                'whatsapp_country_code' => $whatsapp['selection'],
                'whatsapp' => $whatsapp['national'],
            ]);
        }
    }

    public function form(Form $form): Form
    {
        $user = Auth::user();

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Informasi Profil'))
                    ->aside()
                    ->icon('heroicon-o-user-circle')
                    ->description(__('Perbarui foto profil, nama, username, dan nomor WhatsApp Anda.'))
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
                            ->extraAttributes(['class' => 'flex flex-col items-center justify-center'])
                            ->extraInputAttributes([
                                'accept' => 'image/*',
                                'class' => 'avatar-file-input',
                            ])
                            ->extraFieldWrapperAttributes(['class' => 'avatar-upload-centered'])
                            ->alignCenter()
                            ->columnSpanFull(),
                        TextInput::make('first_name')
                            ->label(__('Nama Depan'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $set('full_name', $this->buildFullName($state, $get('mid_name'), $get('last_name')));
                            }),
                        TextInput::make('mid_name')
                            ->label(__('Nama Tengah'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $set('full_name', $this->buildFullName($get('first_name'), $state, $get('last_name')));
                            }),
                        TextInput::make('last_name')
                            ->label(__('Nama Belakang'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $set('full_name', $this->buildFullName($get('first_name'), $get('mid_name'), $state));
                            }),
                        TextInput::make('full_name')
                            ->label(__('Nama Lengkap'))
                            ->disabled()
                            ->dehydrated(false)
                            ->prefixIcon('heroicon-o-user')
                            ->helperText(__('Terisi otomatis dari Nama Depan, Tengah, dan Belakang.'))
                            ->columnSpanFull(),
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->placeholder(__('Masukkan username Anda'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'username', ignorable: $user)
                            ->autocomplete('username'),
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
                                ->prefix(fn (\Filament\Forms\Get $get): string => explode('|', $get('whatsapp_country_code') ?: CountryCallingCodeOptions::defaultSelection())[0])
                                ->placeholder(__('81234567890'))
                                ->live(onBlur: true)
                                ->dehydrateStateUsing(fn ($state, \Filament\Forms\Get $get): string => CountryCallingCodeOptions::toE164($get('whatsapp_country_code'), $state))
                                ->suffixActions([
                                    Action::make('sendOtp')
                                        ->label(__('Kirim Kode OTP'))
                                        ->icon('heroicon-o-paper-airplane')
                                        ->action('sendOtp'),
                                ])
                                ->columnSpanFull(),
                        ])->columns(1)->columnSpanFull(),
                        TextInput::make('otp_code')
                            ->label(__('Kode Verifikasi OTP'))
                            ->numeric()
                            ->length(6)
                            ->placeholder(__('6 digit'))
                            ->live()
                            ->visible(fn () => filled($this->data['whatsapp'] ?? null))
                            ->helperText(__('Masukkan 6 digit kode OTP yang dikirim ke WhatsApp Anda untuk memverifikasi nomor.')),
                    ]),
            ]);
    }

    public function sendOtp(): void
    {
        $phone = CountryCallingCodeOptions::toE164(
            $this->data['whatsapp_country_code'] ?? null,
            $this->data['whatsapp'] ?? null,
        );

        if (strlen($phone) < 10) {
            Notification::make()
                ->title(__('Format nomor WhatsApp tidak valid'))
                ->body(__('Masukkan nomor yang benar, misalnya 81234567890.'))
                ->danger()
                ->send();

            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        WhatsappOtp::updateOrCreate(
            ['whatsapp' => $phone],
            [
                'otp_code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'verified_at' => null,
            ]
        );

        $this->sendWhatsappOtp($phone, $otp);

        Notification::make()
            ->title(__('Kode OTP berhasil dikirim'))
            ->body(__('Kode OTP telah dikirim ke WhatsApp. Kode berlaku selama 5 menit.'))
            ->success()
            ->send();

        Log::info('PersonalInfoComponent: WhatsApp OTP sent', ['whatsapp' => $phone]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Avatar kosong = jangan hapus foto lama (terutama foto Google)
            if (empty($data['avatar_url'])) {
                unset($data['avatar_url']);
            }

            $user = Auth::user();

            // ── WhatsApp OTP ───────────────────────────────────────────────
            $newWhatsapp = $this->normalizeWhatsapp($data['whatsapp'] ?? '');
            $oldWhatsapp = $user->whatsapp ? $this->normalizeWhatsapp($user->whatsapp) : null;
            $otpCode = $data['otp_code'] ?? null;

            unset($data['otp_code']);

            if (filled($newWhatsapp) && $newWhatsapp !== $oldWhatsapp) {
                if (! $this->isOtpValid($newWhatsapp, $otpCode)) {
                    Notification::make()
                        ->title(__('Nomor WhatsApp belum diverifikasi'))
                        ->body(__('Kirim kode OTP lalu masukkan 6 digit kode yang diterima di WhatsApp.'))
                        ->warning()
                        ->send();

                    return;
                }

                $data['whatsapp_verified_at'] = now();
            }

            $data['whatsapp'] = $newWhatsapp ?: null;

            // ── Simpan ────────────────────────────────────────────────────
            $user->update($data);

            $user = $user->fresh();
            $rawAvatar = $user->getRawOriginal('avatar_url');

            $this->form->fill([
                'avatar_url' => filter_var($rawAvatar, FILTER_VALIDATE_URL) ? null : $rawAvatar,
                'first_name' => $user->first_name,
                'mid_name' => $user->mid_name,
                'last_name' => $user->last_name,
                'full_name' => $this->buildFullName($user->first_name, $user->mid_name, $user->last_name),
                'username' => $user->username,
                'whatsapp_country_code' => CountryCallingCodeOptions::split($user->whatsapp)['selection'],
                'whatsapp' => CountryCallingCodeOptions::split($user->whatsapp)['national'],
            ]);

            Notification::make()
                ->title(__('Profil berhasil diperbarui!'))
                ->success()
                ->send();

            if (app()->environment('mobile') || NativeServiceProvider::isNativeMobile()) {
                \Native\Mobile\Notification::new()
                    ->title(__('Profil Diperbarui'))
                    ->message(__('Data pribadi Anda telah berhasil disimpan.'))
                    ->show();
            }

            $this->dispatch('profile-updated');
        } catch (\Exception $e) {
            Log::error('PersonalInfoComponent: Error saving profile', ['error' => $e->getMessage()]);

            Notification::make()
                ->title(__('Gagal memperbarui profil'))
                ->body(__('Terjadi kesalahan saat menyimpan profil Anda. Silakan coba lagi.'))
                ->danger()
                ->send();
        }
    }

    private function isOtpValid(string $phone, mixed $otpCode): bool
    {
        if (empty($otpCode) || mb_strlen((string) $otpCode) !== 6) {
            return false;
        }

        $record = WhatsappOtp::where('whatsapp', $phone)
            ->where('otp_code', (string) $otpCode)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (! $record) {
            return false;
        }

        $record->update(['verified_at' => now()]);

        return true;
    }

    private function buildFullName(?string $first, ?string $mid, ?string $last): string
    {
        return trim(collect([$first, $mid, $last])->filter()->implode(' '));
    }

    private function normalizeWhatsapp(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        // PhoneInput menyimpan nomor baru dalam E.164. Fallback ini menjaga
        // nomor Indonesia lama yang mungkin tersimpan tanpa tanda "+".
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        return '+'.(str_starts_with($digits, '62') ? $digits : '62'.$digits);
    }

    private function sendWhatsappOtp(string $phone, string $otp): void
    {
        try {
            $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
            if (empty($token)) {
                Log::warning('[PersonalInfo] WhatsApp OTP skipped — FONNTE_TOKEN not set');

                return;
            }

            $message = __('whatsapp.otp_message', ['otp' => $otp]);

            Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[PersonalInfo] WhatsApp OTP exception: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('User.livewire.shared.personal-info-component.personal-info-component');
    }
}
