<x-filament-panels::page.simple>
    {{-- Custom: Hidden the default login link area --}}
    {{-- <x-slot name="subheading">
        {{ __('filament-panels::pages/auth/register.actions.login.before') }}
        {{ $this->loginAction }}
    </x-slot> --}}

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <style>
        /* ── Profile picture label: center-align ── */
        .avatar-upload-centered > .fi-fo-field-wrp-label {
            display: flex !important; justify-content: center !important;
            text-align: center !important; width: 100% !important;
        }
        .avatar-upload-centered > .fi-fo-field-wrp-label label {
            text-align: center !important; width: 100% !important;
        }

        /* ── Avatar wrapper — intercept clicks ── */
        .avatar-upload-centered {
            position: relative;
            cursor: pointer;
        }
        /* Block FilePond's own click-to-pick so our modal handles it */
        .avatar-upload-centered .filepond--root,
        .avatar-upload-centered .filepond--drop-label {
            pointer-events: none !important;
        }

        /* ── Scan photo wrappers — intercept clicks ── */
        .document-photo-wrapper,
        .selfie-photo-wrapper,
        .face-scan-wrapper {
            position: relative;
            cursor: pointer;
        }
        .document-photo-wrapper .filepond--root,
        .document-photo-wrapper .filepond--drop-label,
        .selfie-photo-wrapper .filepond--root,
        .selfie-photo-wrapper .filepond--drop-label,
        .face-scan-wrapper .filepond--root,
        .face-scan-wrapper .filepond--drop-label {
            pointer-events: none !important;
        }
    </style>

    {{-- Attach click listeners to avatar & scan photo wrappers after DOM is ready --}}
    <div
        x-data="{}"
        x-init="
            $nextTick(() => {
                const wrapper = document.querySelector('.avatar-upload-centered');
                if (wrapper) {
                    wrapper.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        window.dispatchEvent(new CustomEvent('open-avatar-browse'));
                    });
                }

                const scanMap = [
                    { wrapper: '.document-photo-wrapper', event: 'open-document-scan', detail: { field: 'ktp_photo', mode: 'document', wrapper: '.document-photo-wrapper' } },
                    { wrapper: '.selfie-photo-wrapper', event: 'open-document-scan', detail: { field: 'selfie_photo', mode: 'selfie', wrapper: '.selfie-photo-wrapper' } },
                    { wrapper: '.face-scan-wrapper', event: 'open-face-scan', detail: null },
                ];
                scanMap.forEach(({ wrapper, event, detail }) => {
                    const el = document.querySelector(wrapper);
                    if (!el) return;
                    el.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        window.dispatchEvent(new CustomEvent(event, { detail }));
                    });
                });
            });
        "
        class="hidden"
    ></div>

    {{-- Avatar browse modal (same style as cbir-browse-modal) --}}
    @include('User.components.avatar-browse-modal.avatar-browse-modal', [
        'pondSelector' => '.avatar-upload-centered',
    ])

    {{-- Document / Selfie scan modal (OCR auto-fill) + Face scan modal --}}
    @include('User.components.document-scan-modal.document-scan-modal', [
        'documentWrapper' => '.document-photo-wrapper',
        'selfieWrapper' => '.selfie-photo-wrapper',
    ])
    @include('User.components.face-scan-modal.face-scan-modal', [
        'faceWrapper' => '.face-scan-wrapper',
    ])

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
        <x-filament-panels::form id="form" wire:submit="register">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
