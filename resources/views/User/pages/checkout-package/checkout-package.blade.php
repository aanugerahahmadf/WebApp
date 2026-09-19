<x-filament-panels::page>
    <div class="max-w-3xl mx-auto w-full">
        <form wire:submit.prevent="submit" class="space-y-6">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
