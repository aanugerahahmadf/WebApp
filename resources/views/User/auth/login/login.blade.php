<x-filament-panels::page.simple>
    {{-- Custom: Hidden the default registration link area --}}
    @if (filament()->hasRegistration())
        {{-- <x-slot name="subheading">
            {{ __('filament-panels::pages/auth/login.actions.register.before') }}
            {{ $this->registerAction }}
        </x-slot> --}}
    @endif

    {{-- Back button: kembali ke halaman asal (detail paket/produk) bila ada, jika tidak ke beranda --}}
    <div class="absolute top-4 left-4 z-10">
        <a
            href="{{ $this->getBackUrl() }}"
            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 backdrop-blur-xl hover:bg-white/20 transition-all duration-300 border border-white/20 shadow-lg shadow-black/10"
            title="{{ __('Kembali ke Beranda') }}"
            aria-label="{{ __('Kembali ke Beranda') }}"
        >
            <svg class="w-5 h-5 text-white drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <div
        x-data="{ 
            agreed: $wire.entangle('data.agreement'), 
            remembered: $wire.entangle('data.remember'), 
            loading: false,
            googleUrl: '/auth/google/redirect'
        }"
        x-effect="
            $nextTick(function() {
                let form = $el.querySelector('form');
                if (form && !form.dataset.validationBound) {
                    form.dataset.validationBound = 'true';
                    form.addEventListener('submit', function(e) {
                        if (!(agreed && remembered)) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            
                            new FilamentNotification()
                                .title('{{ __('Perhatian') }}')
                                .body('{{ __('Silakan centang opsi Ingat Saya dan Setujui Syarat & Ketentuan untuk melanjutkan.') }}')
                                .warning()
                                .send();
                        }
                    });
                }
            })
        "
        class="w-full flex flex-col items-center justify-center gap-0 py-0"
    >
        <x-filament-panels::form id="form" wire:submit="authenticate">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
