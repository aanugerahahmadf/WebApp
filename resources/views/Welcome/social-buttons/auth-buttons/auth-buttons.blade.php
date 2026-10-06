@php
    $livewire = (isset($getLivewire) && is_callable($getLivewire)) ? $getLivewire() : ($this ?? null);
    $actions = $livewire ? $livewire->getCachedFormActions() : [];
    // Token mode bernama 'signup', bukan 'register' -- sama dengan partial User.
    // `\Filament\Pages\Auth\Register` dan `*auth.register` tetap apa adanya:
    // itu nama class/route vendor, bukan penamaan kita.
    $authMode = $authMode ?? (
        ($livewire instanceof \Filament\Pages\Auth\Register) ? 'signup' :
        (($livewire instanceof \Filament\Pages\Auth\Login) ? 'login' :
        (request()->routeIs('*register*', '*signup*') ? 'signup' : 'login'))
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

    {{-- BUTTON Continue With Google, Nav Link, & Modal Agreement --}}
    @include('Welcome.social-buttons.social-buttons.social-buttons', [
        'hasParentData' => true,
        'hideCheckboxes' => true,
        'authMode' => $authMode,
    ])
</div>
