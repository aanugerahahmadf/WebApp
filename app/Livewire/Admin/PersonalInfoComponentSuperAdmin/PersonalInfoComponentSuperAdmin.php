<?php

namespace App\Livewire\Admin\PersonalInfoComponentSuperAdmin;

use App\Models\WhatsappOtp\WhatsappOtp;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
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
class PersonalInfoComponentSuperAdmin extends Component implements HasForms
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

            $this->form->fill([
                'avatar_url' => filter_var($rawAvatar, FILTER_VALIDATE_URL) ? null : $rawAvatar,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'mid_name' => $user->mid_name,
                'last_name' => $user->last_name,
                'full_name' => $this->buildFullName($user->first_name, $user->mid_name, $user->last_name),
                'whatsapp' => $this->toLocalNumber($user->whatsapp),
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Informasi Profil'))
                    ->aside()
                    ->icon('heroicon-o-user-circle')
                    ->description(__('Perbarui foto profil, nama, dan nomor WhatsApp Anda.'))
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
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->disabled()
                            ->dehydrated(false)
                            ->prefixIcon('heroicon-o-envelope')
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
                        TextInput::make('whatsapp')
                            ->label(__('Nomor WhatsApp'))
                            ->tel()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-chat-bubble-left-ellipsis')
                            ->prefix('+62')
                            ->placeholder(__('81234567890'))
                            ->live(onBlur: true)
                            ->suffixActions([
                                Action::make('sendOtp')
                                    ->label(__('Kirim Kode OTP'))
                                    ->icon('heroicon-o-paper-airplane')
                                    ->action('sendOtp'),
                            ])
                            ->helperText(__('Masukkan nomor dengan kode negara +62. Perubahan nomor wajib diverifikasi melalui kode OTP yang dikirim ke WhatsApp.')),
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
        $phone = $this->normalizeWhatsapp($this->data['whatsapp'] ?? '');

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

        Log::info('PersonalInfoComponentSuperAdmin: WhatsApp OTP sent', ['whatsapp' => $phone]);
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

            $data['whatsapp'] = '+62 '.ltrim($this->toLocalNumber($this->data['whatsapp'] ?? ''), '0');

            // ── Simpan ────────────────────────────────────────────────────
            $user->update($data);

            $user = $user->fresh();
            $rawAvatar = $user->getRawOriginal('avatar_url');

            $this->form->fill([
                'avatar_url' => filter_var($rawAvatar, FILTER_VALIDATE_URL) ? null : $rawAvatar,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'mid_name' => $user->mid_name,
                'last_name' => $user->last_name,
                'full_name' => $this->buildFullName($user->first_name, $user->mid_name, $user->last_name),
                'whatsapp' => $this->toLocalNumber($user->whatsapp),
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
            Log::error('PersonalInfoComponentSuperAdmin: Error saving profile', ['error' => $e->getMessage()]);

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

    private function toLocalNumber(?string $whatsapp): string
    {
        if (blank($whatsapp)) {
            return '';
        }

        $strip = preg_replace('/[\s\-\(\)\+]/', '', $whatsapp);

        if (str_starts_with((string) $strip, '62')) {
            return ltrim(substr((string) $strip, 2), '0');
        }

        if (str_starts_with((string) $strip, '0')) {
            return ltrim(substr((string) $strip, 1), '0');
        }

        return (string) $strip;
    }

    private function normalizeWhatsapp(string $phone): string
    {
        $phone = $this->toLocalNumber($phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    private function sendWhatsappOtp(string $phone, string $otp): void
    {
        try {
            $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
            if (empty($token)) {
                Log::warning('[PersonalInfoSuperAdmin] WhatsApp OTP skipped — FONNTE_TOKEN not set');

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
            Log::warning('[PersonalInfoSuperAdmin] WhatsApp OTP exception: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('User.livewire.shared.personal-info-component.personal-info-component');
    }
}
