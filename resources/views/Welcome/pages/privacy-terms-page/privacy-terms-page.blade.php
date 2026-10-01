<x-filament-panels::page>
    <div class="grid gap-3">
        <a href="{{ $this->getPrivacyPolicyUrl() }}">
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-shield-check" class="h-6 w-6 text-primary-500" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Kebijakan Privasi') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Bagaimana data dan privasi Anda dikelola.') }}</p>
                    </div>
                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 text-gray-400" />
                </div>
            </x-filament::section>
        </a>

        <a href="{{ $this->getTermsOfServiceUrl() }}">
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-6 w-6 text-primary-500" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Ketentuan Layanan') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Aturan penggunaan layanan aplikasi.') }}</p>
                    </div>
                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 text-gray-400" />
                </div>
            </x-filament::section>
        </a>

        <a href="{{ $this->getWeddingPolicyUrl() }}">
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-sparkles" class="h-6 w-6 text-primary-500" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Kebijakan Aplikasi') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Kebijakan terkait layanan dekorasi pernikahan.') }}</p>
                    </div>
                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 text-gray-400" />
                </div>
            </x-filament::section>
        </a>
    </div>
</x-filament-panels::page>