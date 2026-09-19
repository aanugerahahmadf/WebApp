<?php

namespace App\Livewire\User\AppLockComponent;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * @mixin Component
 */
class AppLockComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public bool $fingerprintEnabled = false;

    public bool $faceEnabled = false;

    public bool $pinEnabled = false;

    protected static int $sort = 50;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Kunci Aplikasi'))
                    ->description(__('Kunci Aplikasi menggunakan sidik jari perangkat, verifikasi wajah AI Core, atau PIN untuk membuka aplikasi. Ini berbeda dari Verifikasi Wajah profil (KYC) yang memindai wajah untuk verifikasi identitas.'))
                    ->aside()
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Section::make(__('Sidik Jari'))
                            ->description(fn () => __('Gunakan sidik jari untuk membuka aplikasi').' — '.($this->fingerprintEnabled ? __('Aktif') : __('Nonaktif')))
                            ->schema([
                                Actions::make([
                                    Actions\Action::make('enableFingerprint')
                                        ->label(__('Aktifkan'))
                                        ->icon('heroicon-m-hand-raised')
                                        ->visible(fn () => ! $this->fingerprintEnabled)
                                        ->action(fn () => $this->setFingerprint(true)),
                                    Actions\Action::make('disableFingerprint')
                                        ->label(__('Nonaktifkan'))
                                        ->icon('heroicon-m-hand-raised')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading(__('Nonaktifkan Sidik Jari'))
                                        ->modalDescription(__('Anda tidak lagi dapat membuka aplikasi menggunakan sidik jari.'))
                                        ->visible(fn () => $this->fingerprintEnabled)
                                        ->action(fn () => $this->setFingerprint(false)),
                                ])->alignment(Alignment::End),
                            ]),
                        Section::make(__('Kunci Aplikasi Wajah'))
                            ->description(fn () => __('Daftarkan wajah untuk membuka kunci aplikasi (AI Core)').' — '.($this->faceEnabled ? __('Aktif') : __('Nonaktif')))
                            ->schema([
                                Actions::make([
                                    Actions\Action::make('enableFace')
                                        ->label(__('Aktifkan'))
                                        ->icon('heroicon-m-face-smile')
                                        ->visible(fn () => ! $this->faceEnabled)
                                        ->action(fn () => $this->setFace(true)),
                                    Actions\Action::make('disableFace')
                                        ->label(__('Nonaktifkan'))
                                        ->icon('heroicon-m-face-smile')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading(__('Nonaktifkan Kunci Aplikasi Wajah'))
                                        ->modalDescription(__('Anda tidak lagi dapat membuka aplikasi menggunakan wajah.'))
                                        ->visible(fn () => $this->faceEnabled)
                                        ->action(fn () => $this->setFace(false)),
                                ])->alignment(Alignment::End),
                            ]),
                        Section::make(__('Kunci PIN'))
                            ->description(fn () => __('Gunakan PIN untuk membuka').' — '.($this->pinEnabled ? __('Aktif') : __('Nonaktif')))
                            ->schema([
                                Actions::make([
                                    Actions\Action::make('managePin')
                                        ->label(fn () => $this->pinEnabled ? __('Ubah PIN') : __('Atur PIN'))
                                        ->icon('heroicon-m-key')
                                        ->modalHeading(__('Kunci PIN'))
                                        ->modalSubmitActionLabel(__('Simpan'))
                                        ->form([
                                            TextInput::make('current_pin')
                                                ->label(__('PIN lama'))
                                                ->password()
                                                ->revealable()
                                                ->length(6)
                                                ->numeric()
                                                ->visible(fn () => $this->pinEnabled)
                                                ->required(fn () => $this->pinEnabled),
                                            TextInput::make('pin')
                                                ->label(__('PIN baru (6 digit)'))
                                                ->password()
                                                ->revealable()
                                                ->length(6)
                                                ->numeric()
                                                ->required(),
                                        ])
                                        ->action(function (array $data) {
                                            $this->storePin($data['pin'], $data['current_pin'] ?? null);
                                        }),
                                    Actions\Action::make('deletePin')
                                        ->label(__('Hapus PIN'))
                                        ->icon('heroicon-m-trash')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading(__('Hapus PIN'))
                                        ->modalDescription(__('Kunci PIN akan dinonaktifkan.'))
                                        ->visible(fn () => $this->pinEnabled)
                                        ->action(fn () => $this->clearPin()),
                                ])->alignment(Alignment::End),
                            ]),
                    ]),
            ]);
    }

    protected function refreshStatus(): void
    {
        $user = Auth::user();
        $this->fingerprintEnabled = (bool) ($user?->app_lock_fingerprint_enabled ?? false);
        $this->faceEnabled = (bool) ($user?->app_lock_face_enabled ?? false);
        $this->pinEnabled = (bool) ($user?->app_lock_pin_enabled ?? false);
    }

    protected function setFingerprint(bool $value): void
    {
        $user = Auth::user();
        $user->app_lock_fingerprint_enabled = $value;
        $user->save();
        $this->refreshStatus();

        Notification::make()
            ->success()
            ->title($value ? __('Sidik jari diaktifkan') : __('Sidik jari dinonaktifkan'))
            ->send();
    }

    protected function setFace(bool $value): void
    {
        $user = Auth::user();
        $user->app_lock_face_enabled = $value;
        $user->save();
        $this->refreshStatus();

        Notification::make()
            ->success()
            ->title($value ? __('Kunci wajah diaktifkan') : __('Kunci wajah dinonaktifkan'))
            ->send();
    }

    protected function storePin(string $pin, ?string $currentPin = null): void
    {
        $user = Auth::user();

        if ($user->app_lock_pin_enabled) {
            $hash = (string) ($user->app_lock_pin_hash ?? '');
            if (! $currentPin || ! Hash::check($currentPin, $hash)) {
                Notification::make()
                    ->danger()
                    ->title(__('PIN lama tidak sesuai'))
                    ->send();

                return;
            }
        }

        $user->app_lock_pin_hash = Hash::make($pin);
        $user->app_lock_pin_enabled = true;
        $user->save();
        $this->refreshStatus();

        Notification::make()
            ->success()
            ->title(__('PIN kunci aplikasi berhasil disimpan'))
            ->send();
    }

    protected function clearPin(): void
    {
        $user = Auth::user();
        $user->app_lock_pin_enabled = false;
        $user->app_lock_pin_hash = null;
        $user->save();
        $this->refreshStatus();

        Notification::make()
            ->success()
            ->title(__('PIN kunci aplikasi dihapus'))
            ->send();
    }

    public function render()
    {
        return view('User.livewire.app-lock-component.app-lock-component');
    }
}
