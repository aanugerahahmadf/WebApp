{{-- Form ganti kata sandi. --}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl">
        <x-filament-panels::form wire:submit="updatePassword">
            {{ $this->form }}

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-filament::button
                    tag="a"
                    wire:navigate
                    :href="\App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(panel: 'user')"
                    color="gray"
                >
                    {{ __('Batal') }}
                </x-filament::button>

                <x-filament::button type="submit" icon="heroicon-m-lock-closed">
                    {{ __('Ubah Kata Sandi') }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </div>
</x-filament-panels::page>