<?php

namespace App\Livewire\User\NotificationSettingsComponent;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Joaopaulolndev\FilamentEditProfile\Concerns\HasSort;
use Livewire\Component;

/**
 * @mixin Component
 */
class NotificationSettingsComponent extends Component implements HasForms
{
    use HasSort;
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $preferences = [];

    protected static int $sort = 26;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function mount(): void
    {
        $this->form->fill(array_replace($this->defaults(), Auth::user()?->notification_preferences ?? []));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Status Notifikasi'))
                    ->description(__('Matikan sementara semua notifikasi tanpa menghapus pilihan kategori Anda.'))
                    ->schema([
                        Toggle::make('enabled')->label(__('Terima notifikasi'))->helperText(__('Jika dimatikan, aplikasi tidak akan mengirim notifikasi baru sampai diaktifkan kembali.'))->live(),
                    ]),
                Section::make(__('Cara Menerima Notifikasi'))
                    ->description(__('Pilih media yang dapat digunakan untuk mengirim pemberitahuan.'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            Toggle::make('in_app')->label(__('Di aplikasi'))->helperText(__('Notifikasi di pusat notifikasi.')),
                            Toggle::make('email')->label(__('Email'))->helperText(__('Ringkasan dan pemberitahuan penting.')),
                            Toggle::make('whatsapp')->label(__('WhatsApp'))->helperText(__('Hanya untuk informasi penting.')),
                        ]),
                    ]),
                Section::make(__('Kategori Notifikasi'))
                    ->description(__('Tentukan informasi apa saja yang ingin Anda terima. Notifikasi keamanan selalu disarankan tetap aktif.'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Toggle::make('chat')->label(__('Chat dan pesan'))->helperText(__('Pesan baru, balasan, dan pembaruan percakapan.')),
                            Toggle::make('booking')->label(__('Pemesanan dan jadwal'))->helperText(__('Status pesanan, jadwal, dan perubahan layanan.')),
                            Toggle::make('payment')->label(__('Pembayaran'))->helperText(__('Tagihan, pembayaran berhasil, refund, dan pengingat pembayaran.')),
                            Toggle::make('vendors')->label(__('Vendor, produk, dan paket'))->helperText(__('Info vendor, paket, dan rekomendasi yang relevan.')),
                            Toggle::make('promotions')->label(__('Promo dan voucher'))->helperText(__('Diskon, voucher, dan penawaran khusus.')),
                            Toggle::make('wishlist')->label(__('Favorit dan wishlist'))->helperText(__('Perubahan ketersediaan atau harga item favorit.')),
                            Toggle::make('reviews')->label(__('Ulasan'))->helperText(__('Ulasan baru dan tanggapan atas ulasan Anda.')),
                            Toggle::make('system')->label(__('Pembaruan sistem'))->helperText(__('Informasi fitur, pemeliharaan, dan pengumuman layanan.')),
                            Toggle::make('security')->label(__('Keamanan akun'))->helperText(__('Sign In baru, perubahan akun, dan aktivitas mencurigakan.')),
                        ]),
                    ]),
                Section::make(__('Waktu dan Frekuensi'))
                    ->description(__('Atur kapan notifikasi non-penting dapat dikirim kepada Anda.'))
                    ->schema([
                        Select::make('digest_frequency')->label(__('Frekuensi ringkasan'))->options(['instant' => __('Langsung saat terjadi'), 'daily' => __('Ringkasan harian'), 'weekly' => __('Ringkasan mingguan')])->required()->native(false),
                        Toggle::make('quiet_hours_enabled')->label(__('Aktifkan waktu hening'))->helperText(__('Notifikasi keamanan dan transaksi penting tetap dapat dikirim.'))->live(),
                        Grid::make(2)->schema([
                            TimePicker::make('quiet_hours_start')->label(__('Mulai waktu hening'))->seconds(false)->visible(fn (callable $get): bool => (bool) $get('quiet_hours_enabled')),
                            TimePicker::make('quiet_hours_end')->label(__('Selesai waktu hening'))->seconds(false)->visible(fn (callable $get): bool => (bool) $get('quiet_hours_enabled')),
                        ]),
                        Toggle::make('sound')->label(__('Suara notifikasi'))->helperText(__('Putar suara ketika notifikasi masuk di perangkat ini.')),
                    ]),
            ])
            ->statePath('preferences');
    }

    public function savePreferences(): void
    {
        $preferences = array_replace($this->defaults(), $this->form->getState());
        $user = Auth::user();

        if (! $user) return;

        $user->update(['notification_preferences' => $preferences]);
        $this->preferences = $preferences;
        Notification::make()->success()->title(__('Pengaturan notifikasi berhasil disimpan.'))->send();
    }

    public function enableAll(): void
    {
        $preferences = $this->form->getState();
        foreach ($this->toggleKeys() as $key) $preferences[$key] = true;
        $this->form->fill($preferences);
    }

    public function disableAll(): void
    {
        $preferences = $this->form->getState();
        foreach ($this->toggleKeys() as $key) $preferences[$key] = false;
        $this->form->fill($preferences);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'enabled' => true, 'in_app' => true, 'email' => true, 'whatsapp' => false,
            'chat' => true, 'booking' => true, 'payment' => true, 'vendors' => true,
            'promotions' => true, 'wishlist' => true, 'reviews' => true, 'system' => true,
            'security' => true, 'digest_frequency' => 'instant', 'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00', 'sound' => true,
        ];
    }

    /** @return list<string> */
    private function toggleKeys(): array
    {
        return ['enabled', 'in_app', 'email', 'whatsapp', 'chat', 'booking', 'payment', 'vendors', 'promotions', 'wishlist', 'reviews', 'system', 'security', 'sound'];
    }

    public function render(): View
    {
        return view('User.livewire.notification-settings-component.notification-settings-component');
    }
}
