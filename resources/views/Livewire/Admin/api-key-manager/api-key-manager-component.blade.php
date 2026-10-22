<div>
    <div class="mb-6">
        {{ $this->form }}
        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="createKey" icon="/images/icons/plus.svg">
                {{ __('Buat API Key') }}
            </x-filament::button>
        </div>
    </div>

    {{ $this->table }}
</div>
