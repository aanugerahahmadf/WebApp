<div>
    <x-filament::section aside icon="heroicon-o-shield-check" :heading="__('Kata Sandi dan Keamanan')" :description="__('Kelola Sign In, pemulihan akun, perangkat, dan pemeriksaan keamanan.')">
        <div class="flex items-center justify-end">
            <x-filament::button
                tag="a"
                wire:navigate
                :href="\App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(panel: 'user')"
                icon="heroicon-m-chevron-right"
                icon-position="after"
                color="primary"
                size="sm"
            >
                {{ __('Buka') }}
            </x-filament::button>
        </div>
    </x-filament::section>
</div>
