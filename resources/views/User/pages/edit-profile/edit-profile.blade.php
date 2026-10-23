<x-filament-panels::page>
    <div class="profile-glass-page edit-profile-page space-y-6">
        @foreach ($this->getRegisteredCustomProfileComponents() as $component)
            @unless(is_null($component))
                @livewire($component, [], key($component))
            @endunless
        @endforeach
    </div>
</x-filament-panels::page>
