<div>
    <x-filament::section aside icon="heroicon-o-language" :heading="__('Bahasa Aplikasi')" :description="__('Pilih bahasa yang ingin Anda gunakan di aplikasi ini.')">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach (config('filament-language-switcher.locals') as $key => $language)
                <button type="button" wire:click="changeLanguage('{{ $key }}')" @class([
                    'flex items-center gap-3 p-3 rounded-xl border transition-all duration-200',
                    'bg-primary-50 border-primary-200 ring-1 ring-primary-500' =>
                        $selectedLocale === $key,
                    'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:border-primary-300' =>
                        $selectedLocale !== $key,
                ])>
                    <div class="w-8 h-6 rounded shadow-sm bg-cover bg-center"
                        style="background-image: url('https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{{ $language['flag'] }}.svg')">
                    </div>
                    <span @class([
                        'text-sm font-medium',
                        'text-primary-700 dark:text-primary-400' => $selectedLocale === $key,
                        'text-gray-700 dark:text-gray-300' => $selectedLocale !== $key,
                    ])>
                        {{ __($language['label']) }}
                    </span>
                </button>
            @endforeach
        </div>
    </x-filament::section>

    @once
        @push('scripts')
            <script>
                document.addEventListener('livewire:init', () => {
                    Livewire.on('locale-changed', () => window.location.reload());
                });
            </script>
        @endpush
    @endonce

</div>
