<x-filament-panels::form wire:submit="updateProfile">
    {{ $this->form }}

    <div class="fi-form-actions">
        <div class="flex flex-row-reverse flex-wrap items-center gap-3 fi-ac">
            <x-filament::button type="submit">
                Simpan Perubahan
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::form>
