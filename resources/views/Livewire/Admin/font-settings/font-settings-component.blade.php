{{--
    Tipografi panel admin.

    Dua pemilih huruf (TOMBOL + modal grid, bukan dropdown select):
      - `storefront_default` : bawaan huruf untuk website + Dekstop App.
      - `admin`              : huruf panel admin + Dekstop Admin App.

    Sisanya (ukuran, tebal, warna, radius, brand, CSS) tetap form biasa.

    Catatan: kedua pemilih MENYIMPAN sendiri ke `fm_app_settings`
    (langsung, tanpa tombol "Simpan"), jadi form di bawah TIDAK boleh
    menimpa nilainya -- `FmAppSetting::saveAppearance()`merge ke nilai
    yang ada, dan data form tidak lagi memuat 'family'/'admin_family'.
--}}
<div>
    {{-- Bawaan huruf website + Dekstop App --}}
    @livewire(\App\Livewire\Shared\FontPicker\FontPicker::class, ['target' => 'storefront_default'])

    {{-- Huruf panel admin + Dekstop Admin App --}}
    @livewire(\App\Livewire\Shared\FontPicker\FontPicker::class, ['target' => 'admin'])

    <form wire:submit="save" class="fi-sc-form">
        {{ $this->form }}

        <div class="flex justify-end mt-4">
            <x-filament::button type="submit">
                {{ __('Simpan Setelan Teks') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
