<?php

namespace App\Livewire\User\SecuritySettingsComponent;

use App\Models\BackupCode\BackupCode;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TrustedDevice\TrustedDevice;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class SecuritySettingsComponent extends Component
{
    public function toggleTwoFactor(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if (! $user->two_factor_enabled && blank($user->whatsapp)) {
            Notification::make()
                ->title(__('Nomor WhatsApp diperlukan'))
                ->body(__('Tambahkan nomor WhatsApp pada profil sebelum mengaktifkan autentikasi dua faktor.'))
                ->warning()
                ->send();

            return;
        }

        $user->two_factor_enabled = ! (bool) $user->two_factor_enabled;
        $user->save();

        if (! $user->two_factor_enabled) {
            BackupCode::query()->where('user_id', $user->id)->delete();
        }

        Notification::make()
            ->title($user->two_factor_enabled ? __('Autentikasi dua faktor diaktifkan') : __('Autentikasi dua faktor dinonaktifkan'))
            ->success()
            ->send();
    }

    public function toggleSavedLogin(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->saved_login_enabled = ! (bool) $user->saved_login_enabled;
        $user->save();

        Notification::make()
            ->title($user->saved_login_enabled ? __('Info login tersimpan diaktifkan') : __('Info login tersimpan dinonaktifkan'))
            ->success()
            ->send();
    }

    public function generateBackupCodes(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->two_factor_enabled) {
            return;
        }

        BackupCode::query()->where('user_id', $user->id)->delete();
        $codes = collect(range(1, 10))->map(function () use ($user): string {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create(['user_id' => $user->id, 'code' => $code]);

            return $code;
        });

        session()->flash('security_backup_codes', $codes->all());
        Notification::make()->title(__('Kode cadangan baru dibuat'))->success()->send();
    }

    public function removeTrustedDevice(int $deviceId): void
    {
        TrustedDevice::query()->where('user_id', Auth::id())->whereKey($deviceId)->delete();
        Notification::make()->title(__('Perangkat tepercaya dihapus'))->success()->send();
    }

    public function render(): View
    {
        $user = Auth::user();
        $devices = $user ? TrustedDevice::query()->where('user_id', $user->id)->latest('trusted_at')->get() : collect();
        $emails = $user ? SecurityEmail::query()->where('user_id', $user->id)->latest('sent_at')->limit(5)->get() : collect();
        $backupCodeCount = $user ? BackupCode::query()->where('user_id', $user->id)->where('used', false)->count() : 0;

        return view('User.livewire.security-settings-component.security-settings-component', compact('user', 'devices', 'emails', 'backupCodeCount'));
    }
}
