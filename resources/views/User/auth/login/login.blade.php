<x-filament-panels::page.simple>
    {{-- Custom: Hidden the default registration link area --}}
    @if (filament()->hasRegistration())
        {{-- <x-slot name="subheading">
            {{ __('filament-panels::pages/auth/login.actions.register.before') }}
            {{ $this->registerAction }}
        </x-slot> --}}
    @endif

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
