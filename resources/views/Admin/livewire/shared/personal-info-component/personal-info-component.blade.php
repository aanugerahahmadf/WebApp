{{--
    PROFIL ADMIN -- POLA SAMA DENGAN PANEL USER (2026-10-26).

    Dua hal yang disamakan dengan resources/views/User/livewire/shared/
    personal-info-component/personal-info-component.blade.php:

    1. Halaman terbuka dalam mode BACA. Semua field terkunci dan yang
       tampil hanya tombol "Edit". Setelah ditekan, field terbuka dan
       tombolnya berganti jadi "Save Changes".

    2. Fieldset dibungkus `x-bind:disabled` yang di-entangle ke
       $wire.isEditing, jadi kunci di sisi klien dan aturan di sisi server
       (disabled() pada schema + guard di save()) berjalan bersamaan.

    Username TIDAK lagi punya kartu sendiri di bawah form: field-nya
    sekarang ikut di section "Informasi Profil"
    (PersonalInfoComponentSuperAdmin::form()).
--}}
<div x-data="{ editing: $wire.entangle('isEditing') }">
    <form wire:submit="save" class="fi-sc-form">
        <fieldset x-bind:disabled="! editing" class="contents">
            {{ $this->form }}
        </fieldset>

        <div class="flex justify-end mt-4 mb-8">
            @if ($isEditing)
                <x-filament::button type="submit">
                    {{ __('Save Changes') }}
                </x-filament::button>
            @else
                <x-filament::button type="button" wire:click="startEditing">
                    {{ __('Edit') }}
                </x-filament::button>
            @endif
        </div>
    </form>

    <x-filament-actions::modals />
</div>
