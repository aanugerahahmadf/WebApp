<div>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="button" wire:click="save" icon="/images/icons/check.svg">
                {{ __('Simpan Semua Pengaturan') }}
            </x-filament::button>
        </div>
    </form>
</div>
