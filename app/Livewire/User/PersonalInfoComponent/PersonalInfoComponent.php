<?php

namespace App\Livewire\User\PersonalInfoComponent;

use App\Support\AppPlatform\AppPlatform;
use App\Support\Phone\WhatsappField;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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

            $firstName = $user->first_name;
            $midName = $user->mid_name;
            $lastName = $user->last_name;

            if (empty($firstName) && filled($user->full_name)) {
                $parts = explode(' ', trim($user->full_name));
                $firstName = array_shift($parts);
                $lastName = count($parts) > 0 ? array_pop($parts) : null;
                $midName = count($parts) > 0 ? implode(' ', $parts) : null;
            }

            $fullName = $this->buildFullName($firstName, $midName, $lastName) ?: $user->full_name;

            $this->form->fill([
                'avatar_url' => filter_var($rawAvatar, FILTER_VALIDATE_URL) ? null : $rawAvatar,
                'first_name' => $firstName,
                'mid_name' => $midName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'username' => $user->username,
                ...WhatsappField::state($user->whatsapp),
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
                        WhatsappField::group(withOtpButton: true),
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
        $phone = WhatsappField::e164(
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

        WhatsappField::sendOtp($phone, 'PersonalInfoComponent');

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
            $newWhatsapp = WhatsappField::normalize($data['whatsapp'] ?? '');
            $oldWhatsapp = $user->whatsapp ? WhatsappField::normalize($user->whatsapp) : null;
            $otpCode = $data['otp_code'] ?? null;

            unset($data['otp_code']);

            if (filled($newWhatsapp) && $newWhatsapp !== $oldWhatsapp) {
                if (! WhatsappField::isOtpValid($newWhatsapp, $otpCode)) {
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
            $data['full_name'] = $this->buildFullName(
                $data['first_name'] ?? $user->first_name,
                $data['mid_name'] ?? $user->mid_name,
                $data['last_name'] ?? $user->last_name,
            );

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
                ...WhatsappField::state($user->whatsapp),
            ]);

            Notification::make()
                ->title(__('Profil berhasil diperbarui!'))
                ->success()
                ->send();

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

    private function buildFullName(?string $first, ?string $mid, ?string $last): string
    {
        return trim(collect([$first, $mid, $last])->filter()->implode(' '));
    }

    public function render(): View
    {
        return view('User.livewire.shared.personal-info-component.personal-info-component');
    }
}
