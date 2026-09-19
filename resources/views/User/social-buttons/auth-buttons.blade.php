@php
    $livewire = (isset($getLivewire) && is_callable($getLivewire)) ? $getLivewire() : ($this ?? null);
    $actions = $livewire ? $livewire->getCachedFormActions() : [];
    $authMode = $authMode ?? (
        ($livewire instanceof \Filament\Pages\Auth\Register) ? 'register' :
        (($livewire instanceof \Filament\Pages\Auth\Login) ? 'login' :
        (request()->routeIs('*register*') ? 'register' : 'login'))
    );
@endphp

<div class="w-full flex flex-col gap-3 pt-2">
    {{-- BUTTON Log In / Register (Native Filament Form Action) --}}
    @if (count($actions))
        <div class="w-full">
            <x-filament::actions
                :actions="$actions"
                :full-width="true"
            />
        </div>
    @endif

    {{-- BUTTON Sign in with Google, Nav Link, & Modal Agreement --}}
    @include('User.social-buttons.social-buttons', [
        'hasParentData' => true,
        'hideCheckboxes' => true,
        'authMode' => $authMode,
    ])
</div>
