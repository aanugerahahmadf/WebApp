    @php
        try {
            $termsRecord = \App\Models\TermsOfService\TermsOfService::first();
            $privacyRecord = \App\Models\PrivacyPolicy\PrivacyPolicy::first();
            $weddingPolicyRecord = \App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy::first();
        } catch (\Throwable $e) {
            $termsRecord = null;
            $privacyRecord = null;
            $weddingPolicyRecord = null;
        }
    @endphp

    <x-filament::modal id="agreement-modal" width="3xl" :close-by-clicking-away="false" :close-button="false">
        <div x-data="{ step: 1, mode: 'wizard', showWedding: false }"
            x-on:open-agreement.window="mode = $event.detail.mode || 'wizard'; step = $event.detail.step || 1; $dispatch('open-modal', { id: 'agreement-modal' })"
            class="relative overflow-hidden min-h-[450px]">

            {{-- Terms of Service Content --}}
            <div x-show="mode === 'terms' || (mode === 'wizard' && step === 1)"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-500 absolute w-full"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-full" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-primary-500">
                        {{ __($termsRecord?->title ?? 'Perjanjian Pengguna') }}</h2>
                </div>

                <div class="space-y-6 text-left py-2 max-h-[60vh] overflow-y-auto no-scrollbar">
                    @forelse ($termsRecord?->content ?? [] as $i => $item)
                        <article class="space-y-2">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">
                                {{ $i + 1 }}. {{ __($item['heading']) }}</h3>
                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400 text-justify">
                                {!! nl2br(e(__($item['body']))) !!}</p>
                        </article>
                    @empty
                        <p class="text-sm text-gray-400 italic">{{ __('Konten belum tersedia.') }}</p>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 gap-3">
                    <template x-if="mode === 'wizard'">
                        <x-filament::button color="primary" @click="step = 2">
                            {{ __('Lanjutkan') }}
                        </x-filament::button>
                    </template>
                    <template x-if="mode === 'terms'">
                        <x-filament::button color="gray" outlined
                            @click="$dispatch('close-modal', { id: 'agreement-modal' })">
                            {{ __('Tutup') }}
                        </x-filament::button>
                    </template>
                </div>
            </div>

            {{-- Privacy Policy Content --}}
            <div x-show="mode === 'privacy' || (mode === 'wizard' && step === 2)"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-500 absolute w-full"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-full" class="space-y-4" style="display: none;">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <template x-if="mode === 'wizard'">
                            <button @click="step = 1" class="text-gray-400 hover:text-primary-500 transition-colors">
                                <x-heroicon-m-arrow-left class="w-6 h-6" />
                            </button>
                        </template>
                        <h2 class="text-xl font-bold text-primary-500">
                            {{ __($privacyRecord?->title ?? 'Kebijakan Privasi') }}</h2>
                    </div>
                </div>

                <div class="space-y-6 text-left py-2 max-h-[60vh] overflow-y-auto no-scrollbar">
                    @forelse ($privacyRecord?->content ?? [] as $i => $item)
                        <article class="space-y-2">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">
                                {{ $i + 1 }}. {{ __($item['heading']) }}</h3>
                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400 text-justify">
                                {!! nl2br(e(__($item['body']))) !!}</p>
                        </article>
                    @empty
                        <p class="text-sm text-gray-400 italic">{{ __('Konten belum tersedia.') }}</p>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 gap-3">
                    <template x-if="mode === 'wizard'">
                        <x-filament::button color="primary" @click="step = 3">
                            {{ __('Lanjutkan') }}
                        </x-filament::button>
                    </template>
                    <template x-if="mode === 'privacy'">
                        <x-filament::button color="gray" outlined
                            @click="$dispatch('close-modal', { id: 'agreement-modal' })">
                            {{ __('Tutup') }}
                        </x-filament::button>
                    </template>
                </div>
            </div>

            {{-- Wedding Decoration Policy Content --}}
            <div x-show="mode === 'wedding_policy' || (mode === 'wizard' && step === 3)"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-500 absolute w-full"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-full" class="space-y-4" style="display: none;">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <template x-if="mode === 'wizard'">
                            <button @click="step = 2" class="text-gray-400 hover:text-primary-500 transition-colors">
                                <x-heroicon-m-arrow-left class="w-6 h-6" />
                            </button>
                        </template>
                        <h2 class="text-xl font-bold text-primary-500">
                            {{ __($weddingPolicyRecord?->title ?? 'Kebijakan Aplikasi') }}</h2>
                    </div>
                </div>

                <div class="space-y-6 text-left py-2 max-h-[60vh] overflow-y-auto no-scrollbar">
                    @forelse ($weddingPolicyRecord?->content ?? [] as $i => $item)
                        <article class="space-y-2">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">
                                {{ $i + 1 }}. {{ __($item['heading']) }}</h3>
                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400 text-justify">
                                {!! nl2br(e(__($item['body']))) !!}</p>
                        </article>
                    @empty
                        <p class="text-sm text-gray-400 italic">{{ __('Konten belum tersedia.') }}</p>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 gap-3">
                    <template x-if="mode === 'wizard'">
                        <x-filament::button color="primary"
                            @click="agreed = true; $dispatch('close-modal', { id: 'agreement-modal' })">
                            {{ __('Saya Mengerti & Setuju') }}
                        </x-filament::button>
                    </template>
                    <template x-if="mode === 'wedding_policy'">
                        <x-filament::button color="gray" outlined
                            @click="$dispatch('close-modal', { id: 'agreement-modal' })">
                            {{ __('Tutup') }}
                        </x-filament::button>
                    </template>
                </div>
            </div>
        </div>
    </x-filament::modal>
