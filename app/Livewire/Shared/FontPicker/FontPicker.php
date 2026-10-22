<?php

namespace App\Livewire\Shared\FontPicker;

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppFont\AppFont;
use App\Support\ViewerFont\ViewerFont;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pemilih huruf (font picker) -- SATU komponen untuk tiga target.
 *
 * Kenapa tombol + modal, bukan `Select` searchable:
 *
 *   - Daftar keluarganya 246+ (satu keluarga per berkas font yang terpasang,
 *     hasil `scripts/generate-fonts.py`). Dropdown select jadi memuat 246
 *     baris yang harus digulir, dan isinya cuma teks -- orang tidak bisa
 *     melihat bentuk hurufnya.
 *   - Grid di modal menampilkan NAMA huruf dengan bentuknya sendiri, jadi
 *     pilihannya terlihat, bukan cuma dibaca.
 *   - Letaknya di halaman Pengaturan, bukan di topbar: huruf adalah
 *     pengaturan yang jarang diubah, dan topbar sudah padat (pencarian,
 *     bahasa, tema, akun).
 *
 * Target:
 *   - `viewer`             : pilihan pribadi pengunjung (storefront).
 *                            Tamu -> session; user -> `users.font_preference`.
 *   - `storefront_default` : default global storefront (diatur admin).
 *   - `admin`              : huruf panel admin (Dekstop Admin App).
 *
 * Ketiganya satu komponen karena sumber daftarnya sama:
 * `AppFont::families()` (katalog aset font lokal).
 *
 * @mixin Component
 */
class FontPicker extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /**
     * Target yang lagi disetel: 'viewer' | 'storefront_default' | 'admin'.
     */
    public string $target = 'viewer';

    /**
     * Action utama: membuka modal berisi grid huruf.
     */
    public function pickFontAction(): Action
    {
        return Action::make('pickFont')
            ->label($this->actionLabel())
            ->icon('/images/icons/text.svg')
            ->modalHeading(__('Pilih Huruf'))
            ->modalDescription(__('Semua huruf di bawah sudah terpasang lokal di aplikasi -- tidak ada yang diunduh dari internet.'))
            ->modalWidth('5xl')
            ->modalContent(fn (): View => view('Livewire.Shared.font-picker.font-picker-grid', [
                'families' => ViewerFont::families(),
                'current' => $this->currentFamily(),
            ]))
            // Tanpa form: tidak ada input yang perlu "submit". Grid-nya
            // yang jadi pemicunya -- lihat applyFont().
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Tutup'));
    }

    /**
     * Pilih satu keluarga huruf.
     */
    public function applyFont(string $family): void
    {
        $ok = match ($this->target) {
            'storefront_default' => $this->saveGlobal('family', $family),
            'admin' => $this->saveGlobal('admin_family', $family),
            default => ViewerFont::set($family),
        };

        if (! $ok) {
            Notification::make()
                ->title(__('Huruf tidak dikenal'))
                ->body(__('Pilih salah satu huruf yang ada di daftar.'))
                ->danger()
                ->send();

            return;
        }

        // Tutup modal, lalu minta halaman dimuat ulang: huruf baru ditulis
        // ke CSS server-side (`--app-font-family`), jadi tanpa muat ulang
        // layarnya masih huruf yang lama.
        $this->unmountAction();

        $this->dispatch('font-picker-applied');

        Notification::make()
            ->title(__('Huruf berhasil diubah'))
            ->success()
            ->send();
    }

    /**
     * Kembalikan ke default (hapus pilihan pribadi / global).
     */
    public function resetFont(): void
    {
        if ($this->target === 'storefront_default' || $this->target === 'admin') {
            $key = $this->target === 'admin' ? 'admin_family' : 'family';
            $this->saveGlobal($key, AppFont::DEFAULT_FAMILY);
        } else {
            ViewerFont::reset();
        }

        $this->unmountAction();
        $this->dispatch('font-picker-applied');

        Notification::make()->title(__('Huruf dikembalikan ke bawaan'))->success()->send();
    }

    /**
     * Huruf yang sedang aktif untuk target ini.
     */
    public function currentFamily(): string
    {
        return match ($this->target) {
            'storefront_default' => AppFont::family(),
            'admin' => AppFont::adminFamily(),
            default => ViewerFont::current(),
        };
    }

    /**
     * Simpan ke pengaturan global (tabel fm_app_settings).
     */
    private function saveGlobal(string $key, string $family): bool
    {
        $families = AppFont::families();

        if (! in_array($family, $families, true)) {
            return false;
        }

        $appearance = FmAppSetting::appearance();
        $appearance[$key] = $family;

        FmAppSetting::saveAppearance($appearance);
        AppFont::forgetCache();

        return true;
    }

    private function actionLabel(): string
    {
        return match ($this->target) {
            'storefront_default' => __('Ubah Huruf Website'),
            'admin' => __('Ubah Huruf Panel Admin'),
            default => __('Ubah Huruf'),
        };
    }

    public function heading(): string
    {
        return match ($this->target) {
            'storefront_default' => __('Huruf Website & Dekstop App'),
            'admin' => __('Huruf Panel Admin & Dekstop Admin App'),
            default => __('Huruf Tampilan'),
        };
    }

    public function description(): string
    {
        return match ($this->target) {
            'storefront_default' => __('Bawaan huruf untuk semua pengunjung website dan aplikasi desktop. Pengunjung tetap bisa memilih hurufnya sendiri di halaman Pengaturan.'),
            'admin' => __('Huruf khusus panel kerja admin. Tidak mengubah tampilan pembeli di website dan Dekstop App.'),
            default => __('Pilih huruf yang ingin dipakai di website dan aplikasi desktop. Pilihan ini hanya berlaku di perangkat ini.'),
        };
    }

    public function render(): View
    {
        return view('Livewire.Shared.font-picker.font-picker');
    }
}
