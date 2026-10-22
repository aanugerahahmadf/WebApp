<?php

namespace App\Livewire\Admin\ApiKeyManagerComponent;

use App\Models\ApiKey\ApiKey;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Pengelola API Key (Sanctum) untuk admin yang sedang login.
 *
 * Dibuat sebagai komponen Livewire (bukan ManageRecords Filament) karena
 * modal CreateAction bawaan Filament tidak merender form di lingkungan ini.
 * Dengan form inline + tombol Simpan, pembuatan key tetap fungsional tanpa
 * bergantung pada modal.
 *
 * Token polos HANYA ditampilkan sekali (di notifikasi) setelah dibuat --
 * Sanctum cuma menyimpan hash-nya, jadi setelah itu tidak bisa diambil lagi.
 */
class ApiKeyManagerComponent extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = ['name' => '', 'abilities' => ['*'], 'expires_in_days' => '30'];
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Buat API Key Baru'))
                    ->description(__('Token hanya ditampilkan SEKALI setelah dibuat. Simpan di tempat aman.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nama Key'))
                            ->placeholder(__('Contoh: Integrasi Mobile App'))
                            ->required()
                            ->maxLength(255),

                        CheckboxList::make('abilities')
                            ->label(__('Abilities'))
                            ->options([
                                '*' => __('Akses penuh (*)'),
                                'read' => __('Baca data (read)'),
                                'write' => __('Tulis data (write)'),
                                'delete' => __('Hapus data (delete)'),
                            ])
                            ->columns(2)
                            ->default(['*']),

                        Select::make('expires_in_days')
                            ->label(__('Kedaluwarsa'))
                            ->options([
                                '7' => __('7 hari'),
                                '30' => __('30 hari'),
                                '90' => __('90 hari'),
                                '365' => __('1 tahun'),
                                'never' => __('Tidak pernah'),
                            ])
                            ->default('30')
                            ->required()
                            ->native(false),
                    ])->columns(1),
            ]);
    }

    public function createKey(): void
    {
        $data = $this->form->getState();

        $admin = \Filament\Facades\Filament::auth()->user();

        $abilities = (array) ($data['abilities'] ?? ['*']);
        if (empty($abilities)) {
            $abilities = ['*'];
        }

        $expiresAt = match ($data['expires_in_days'] ?? '30') {
            'never' => null,
            default => now()->addDays((int) $data['expires_in_days']),
        };

        $token = $admin->createToken((string) $data['name'], $abilities, $expiresAt);

        Notification::make()
            ->success()
            ->title(__('API Key berhasil dibuat'))
            ->body(__('Token (simpan sekarang, tidak akan ditampilkan lagi):') . "\n" . $token->plainTextToken)
            ->persistent()
            ->send();

        $this->data = ['name' => '', 'abilities' => ['*'], 'expires_in_days' => '30'];
        $this->form->fill($this->data);

        // refresh table
        $this->resetTable();
    }

    public function revokeKey(int $id): void
    {
        $admin = \Filament\Facades\Filament::auth()->user();

        ApiKey::query()
            ->where('tokenable_type', $admin ? get_class($admin) : '')
            ->where('tokenable_id', $admin ? $admin->getKey() : 0)
            ->where('id', $id)
            ->delete();

        Notification::make()
            ->success()
            ->title(__('API Key dicabut'))
            ->send();

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $admin = \Filament\Facades\Filament::auth()->user();

        return $table
            ->query(
                ApiKey::query()
                    ->where('tokenable_type', $admin ? get_class($admin) : '')
                    ->where('tokenable_id', $admin ? $admin->getKey() : 0)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nama'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(AppIcon::API_KEY),

                TextColumn::make('abilities')
                    ->label(__('Abilities'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->color('info'),

                TextColumn::make('last_used_at')
                    ->label(__('Terakhir Dipakai'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('Belum pernah')),

                TextColumn::make('expires_at')
                    ->label(__('Kedaluwarsa'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('Tidak pernah'))
                    ->color(fn ($state): string => $state && $state->isPast() ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('revoke')
                    ->label(__('Cabut'))
                    ->icon('/images/icons/trash.svg')
                    ->color('danger')
                    ->requiresConfirmation(false)
                    ->action(fn (ApiKey $record) => $this->revokeKey($record->id)),
            ])
            ->emptyStateHeading(__('Belum ada API Key'))
            ->emptyStateDescription(__('Buat API Key pertama Anda dengan form di atas.'))
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }

    public function render(): View
    {
        return view('Livewire.Admin.api-key-manager.api-key-manager-component');
    }
}
