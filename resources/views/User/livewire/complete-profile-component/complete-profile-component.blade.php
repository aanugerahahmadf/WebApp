<div>
    <style>
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

    {{-- Attach click listeners to scan photo wrappers after DOM is ready --}}
    <div
        x-data="{}"
        x-init="
            $nextTick(() => {
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

    {{-- Document / Selfie scan modal (OCR auto-fill) + Face scan modal --}}
    @include('User.components.document-scan-modal.document-scan-modal', [
        'documentWrapper' => '.document-photo-wrapper',
        'selfieWrapper' => '.selfie-photo-wrapper',
    ])
    @include('User.components.face-scan-modal.face-scan-modal', [
        'faceWrapper' => '.face-scan-wrapper',
        'aiVerification' => true,
        'initialAiResult' => $faceAiResult,
    ])

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" wire:loading.remove wire:target="save" class="w-full">
                {{ __('Simpan & Lanjutkan') }}
            </x-filament::button>
            <div wire:loading wire:target="save" class="w-full">
                <x-filament::button type="button" disabled class="w-full">
                    {{ __('Menyimpan...') }}
                </x-filament::button>
            </div>
        </div>
    </form>

    <x-filament-actions::modals />
</div>