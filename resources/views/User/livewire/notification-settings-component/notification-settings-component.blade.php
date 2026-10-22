<div>
    <x-filament::section
        aside
        icon="/images/icons/bell_alert.svg"
        :heading="__('Pengaturan Notifikasi')"
        :description="__('Atur kategori, media pengiriman, dan waktu notifikasi yang ingin Anda terima.')"
    >
        <x-filament-panels::form wire:submit="savePreferences">
            {{ $this->form }}

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-filament::button type="button" wire:click="disableAll" color="gray" icon="/images/icons/bell_slash.svg">
                    {{ __('Nonaktifkan Semua') }}
                </x-filament::button>
                <x-filament::button type="button" wire:click="enableAll" color="primary" outlined icon="/images/icons/bell_alert.svg">
                    {{ __('Aktifkan Semua') }}
                </x-filament::button>
                <x-filament::button type="submit" icon="/images/icons/check.svg">
                    {{ __('Simpan Pengaturan') }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>
</div>
