<?php

namespace App\Livewire\Shared\BrowserSessionsComponent;

use Carbon\Carbon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Jenssegers\Agent\Agent;
use Livewire\Component;

/**
 * @mixin Component
 */
class BrowserSessionsComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $selectedSessions = [];

    public $selectAll = false;

    protected static int $sort = 50;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Sesi Browser'))
                    ->description(__('Kelola dan keluar dari sesi aktif Anda di browser dan perangkat lain.'))
                    ->aside()
                    ->icon('heroicon-o-computer-desktop')
                    ->schema([
                        Forms\Components\ViewField::make('browserSessions')
                            ->label(__('Sesi Browser'))
                            ->hiddenLabel()
                            ->view('User.forms.components.browser-sessions.browser-sessions')
                            ->viewData([
                                'data' => self::getSessions(),
                                'selectedSessions' => $this->selectedSessions,
                                'selectAll' => $this->selectAll,
                            ]),
                        Actions::make([
                            Actions\Action::make('deleteBrowserSessions')
                                ->label(__('Keluar dari Sesi Browser Lain'))
                                ->requiresConfirmation()
                                ->modalHeading(__('Keluar dari Sesi Browser Lain'))
                                ->modalDescription(__('Silakan masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda ingin keluar dari sesi browser lain di semua perangkat Anda.'))
                                ->modalSubmitActionLabel(__('Keluar dari Sesi Browser Lain'))
                                ->form([
                                    Forms\Components\TextInput::make('password')
                                        ->password()
                                        ->revealable()
                                        ->label(__('Kata Sandi'))
                                        ->required(),
                                ])
                                ->action(function (array $data): void {
                                    self::logoutOtherBrowserSessions($data['password']);
                                })
                                ->modalWidth('2xl'),
                        ])->alignment(Alignment::End),

                    ]),
            ]);
    }

    public static function getSessions(): array
    {
        if (config(key: 'session.driver') !== 'database') {
            return [];
        }

        return collect(
            value: DB::connection(config(key: 'session.connection'))->table(table: config(key: 'session.table', default: 'sessions'))
                ->where(column: 'user_id', operator: Auth::user()->getAuthIdentifier())
                ->latest(column: 'last_activity')
                ->get(['*'])
        )->map(callback: function ($session): object {
            $agent = self::createAgent($session);
            $device = $agent->device();

            return (object) [
                'device' => [
                    'browser' => $agent->browser(),
                    'desktop' => $agent->isDesktop(),
                    'mobile' => $agent->isMobile(),
                    'tablet' => $agent->isTablet(),
                    'platform' => $agent->platform(),
                    'device_name' => ($device === 'WebKit') ? null : $device,
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === request()->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'id' => $session->id,
                'label' => $session->label ?? null,
            ];
        })->toArray();
    }

    protected static function createAgent(mixed $session)
    {
        return tap(
            value: new Agent,
            callback: fn ($agent) => $agent->setUserAgent(userAgent: $session->user_agent)
        );
    }

    public static function logoutOtherBrowserSessions($password): void
    {
        if (! Hash::check($password, Auth::user()->password)) {
            Notification::make()
                ->danger()
                ->title(__('Kata sandi yang diberikan tidak cocok dengan catatan kami.'))
                ->send();

            return;
        }

        /** @var SessionGuard $guard */
        $guard = Auth::guard(Filament::getAuthGuard());
        $guard->logoutOtherDevices($password);

        request()->session()->put([
            'password_hash_'.Filament::getAuthGuard() => Auth::user()->getAuthPassword(),
        ]);

        self::deleteOtherSessionRecords();

        Notification::make()
            ->success()
            ->title(__('Berhasil keluar dari sesi browser lainnya.'))
            ->send();
    }

    protected static function deleteOtherSessionRecords()
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', Auth::user()->getAuthIdentifier())
            ->where('id', '!=', request()->session()->getId())
            ->delete();
    }

    public function deleteSession($id): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', Auth::user()->getAuthIdentifier())
            ->where('id', $id)
            ->delete();

        // Remove from selection if deleted
        $this->selectedSessions = array_diff($this->selectedSessions, [$id]);

        Notification::make()
            ->success()
            ->title(__('Sesi berhasil dihapus.'))
            ->send();
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedSessions = collect(self::getSessions())
                ->where('is_current_device', false)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedSessions = [];
        }
    }

    public function deleteSelectedSessions(): void
    {
        if (empty($this->selectedSessions)) {
            return;
        }

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', Auth::user()->getAuthIdentifier())
            ->whereIn('id', $this->selectedSessions)
            ->delete();

        $count = count($this->selectedSessions);
        $this->selectedSessions = [];
        $this->selectAll = false;

        Notification::make()
            ->success()
            ->title(__(':count sesi berhasil dihapus.', ['count' => $count]))
            ->send();
    }

    public function render()
    {
        return view('User.livewire.shared.browser-sessions-form.browser-sessions-form');
    }
}
