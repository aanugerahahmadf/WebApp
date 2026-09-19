<div>
    <form wire:submit="save" class="fi-sc-form">
        {{ $this->form }}

        <div class="flex justify-end mt-4">
            <x-filament::button type="submit">
                {{ __('Simpan Perubahan') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />

    {{-- Tap-to-pick overlay trigger --}}
    <style>
        .avatar-upload-centered > .fi-fo-field-wrp-label { display: flex !important; justify-content: center !important; text-align: center !important; width: 100% !important; }
        .avatar-upload-centered > .fi-fo-field-wrp-label label { text-align: center !important; width: 100% !important; }
        /* Intercept clicks on the FilePond avatar area before FilePond opens its own picker */
        .avatar-upload-centered .filepond--root,
        .avatar-upload-centered .filepond--drop-label {
            pointer-events: none !important;
        }
        .avatar-upload-centered {
            position: relative;
            cursor: pointer;
        }
    </style>

    {{-- Inject a transparent overlay directly onto the FilePond root via Alpine --}}
    <div
        x-data="{}"
        x-init="
            $nextTick(() => {
                const wrapper = document.querySelector('.avatar-upload-centered');
                if (!wrapper) return;
                wrapper.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    window.dispatchEvent(new CustomEvent('open-avatar-browse'));
                });
            });
        "
        class="hidden"
    ></div>

    {{-- Avatar browse modal (same style as cbir-browse-modal) --}}
    @include('User.components.avatar-browse-modal.avatar-browse-modal', [
        'pondSelector' => '.avatar-upload-centered',
    ])
</div>
