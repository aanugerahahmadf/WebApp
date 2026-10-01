<div>
    <x-filament::section
        aside
        icon="heroicon-o-bell-alert"
        :heading="__('Pengaturan Notifikasi')"
        :description="__('Atur kategori, media pengiriman, dan waktu notifikasi yang ingin Anda terima.')"
    >
        <x-filament-panels::form wire:submit="savePreferences">
            {{ $this->form }}

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-filament::button type="button" wire:click="disableAll" color="gray" icon="heroicon-m-bell-slash">
                    {{ __('Nonaktifkan Semua') }}
                </x-filament::button>
                <x-filament::button type="button" wire:click="enableAll" color="primary" outlined icon="heroicon-m-bell-alert">
                    {{ __('Aktifkan Semua') }}
                </x-filament::button>
                <x-filament::button type="submit" icon="heroicon-m-check">
                    {{ __('Simpan Pengaturan') }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>
</div>
