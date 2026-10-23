<div class="space-y-6">
    <form wire:submit="save">
        {{ $this->form }}

        {{-- Dua aksi TANGGAL pakai: perubahan select SUDAH otomatis
             tersimpan (pola WordPress Customizer). Save eksplisit untuk
             admin yang suka menekan sempan-tombol; Reset mengembalikan
             halaman aktif ke semuanya ikut tema. --}}
        <div class="mt-6 flex justify-end gap-3">
            <x-filament::button type="button" color="gray" wire:click="resetPage" icon="heroicon-m-arrow-path">
                {{ __('Reset') }}
            </x-filament::button>

            <x-filament::button type="button" wire:click="save" icon="/images/icons/check.svg">
                {{ __('Simpan Pengaturan Tampilan') }}
            </x-filament::button>
        </div>
    </form>
</div>
