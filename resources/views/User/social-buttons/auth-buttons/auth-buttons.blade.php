@php
    $livewire = (isset($getLivewire) && is_callable($getLivewire)) ? $getLivewire() : ($this ?? null);
    $actions = $livewire ? $livewire->getCachedFormActions() : [];
    // Token mode bernama 'signup', bukan 'register': class-nya sudah di-rename
    // SignIn/SignUp (lihat App\Filament\User\Auth\SignUp\SignUp), jadi
    // partial ini tidak lagi memakai kata "register" untuk mode-nya sendiri.
    //
    // Yang tetap "Register" dan tidak boleh diganti: nama class Filament
    // `\Filament\Pages\Auth\Register` (base class yang di-`extends`) dan
    // nama route `filament.*.auth.register` -- keduanya kontrak vendor.
    // Pengecekan route purposely menerima KEDUA slug: `/register` (default
    // Filament) dan `/signup` (slug lama project ini sebelum
    // `->registrationRouteSlug('signup')` di-comment-kan).
    $authMode = $authMode ?? (
        ($livewire instanceof \Filament\Pages\Auth\Register) ? 'signup' :
        (($livewire instanceof \Filament\Pages\Auth\Login) ? 'login' :
        (request()->routeIs('*register*', '*signup*') ? 'signup' : 'login'))
    );
@endphp

<div class="w-full flex flex-col gap-3 pt-2">
    {{-- BUTTON Log In / Sign Up (Native Filament Form Action) --}}
    @if (count($actions))
        <div class="w-full">
            <x-filament::actions
                :actions="$actions"
                :full-width="true"
            />
        </div>
    @endif

    {{-- BUTTON Continue With Google, Nav Link, & Modal Agreement --}}
    {{-- $showGoogleButton: default-nya FALSE. Partial ini dipakai SignIn,
         yang sudah tidak lagi memuat tombol Google --
         halaman auth baru (/user/auth, App\Filament\User\Auth\Auth\Auth)
         yang Pegangnya sekarang. Blade partial tidak punya nilai default,
         jadi NULL harus dipetakan ke false di sini, bukan dibiarkan jatuh
         ke default true milik social-buttons. --}}
    @include('User.social-buttons.social-buttons.social-buttons', [
        'hasParentData' => true,
        'hideCheckboxes' => true,
        'authMode' => $authMode,
        'showGoogleButton' => $showGoogleButton ?? false,
    ])
</div>
