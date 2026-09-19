<?php

namespace App\Filament\Admin\Auth\Register;

use App\Models\User\User;
use App\Services\GeoLocationService\GeoLocationService;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;

class Register extends BaseRegister
{
    public function getHeading(): string|Htmlable
    {
        return __('Daftar Akun Baru');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Silakan isi formulir di bawah ini untuk bergabung dengan kami.');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label(__('Alamat Email'));
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label(__('Kata Sandi'));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label(__('Konfirmasi Kata Sandi'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Step::make('akun')
                        ->label(__('Akun'))
                        ->description(__('Info akun dasar'))
                        ->icon('heroicon-m-user-circle')
                        ->schema([
                            TextInput::make('username')
                                ->label(__('Username'))
                                ->required()
                                ->unique(User::class)
                                ->maxLength(255),
                            $this->getEmailFormComponent(),
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),
                    Step::make('detail_pribadi')
                        ->label(__('Detail Pribadi'))
                        ->description(__('Info kontak Anda'))
                        ->icon('heroicon-m-identification')
                        ->schema([
                            TextInput::make('first_name')
                                ->label(__('Nama Depan'))
                                ->required(),
                            TextInput::make('last_name')
                                ->label(__('Nama Belakang'))
                                ->required(),
                            TextInput::make('phone')
                                ->label(__('Nomor Telepon'))
                                ->tel()
                                ->required(),
                            Textarea::make('address')
                                ->label(__('Alamat Lengkap'))
                                ->required()
                                ->rows(3),
                        ]),
                ])
                    ->submitAction(new HtmlString('<button type="submit" style="background-color: #e11d48; color: white; padding: 0.5rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor=\'#be123c\'" onmouseout="this.style.backgroundColor=\'#e11d48\'">'.__('Daftar').'</button>')),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRegistration(array $data): User
    {
        $ip = request()->ip();

        $user = User::create([
            'full_name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'ip_address' => $ip,
        ]);

        // Assign customer role automatically
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
}
