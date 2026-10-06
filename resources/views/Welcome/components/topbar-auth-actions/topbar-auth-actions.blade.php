{{--
    Top-right auth actions for the 'welcome' panel.

    Sits where Filament renders the user menu, minus the avatar. Satu kontrol
    untuk dua status, keduanya memakai ikon user yang sama:

      tamu        -> ikon membuka dropdown, isinya satu aksi: Sign In to
                     Account (id: Masuk ke Akun) ke /user/auth.
      sudah login -> ikon itu sendiri link LANGSUNG ke /user/home (home panel
                     user). Tidak ada lagi tombol "Beranda" terpisah: user yang
                     sudah login tidak butuh dua kontrol untuk satu tujuan, dan
                     /welcome + /welcome/home sudah otomatis dialihkan ke
                     /user/home -- jadi label "Beranda" di sini cuma mengulang
                     halaman yang sudah ditinggalkan.

    Keduanya menunjuk ke panel user, tempat semua halaman auth dan profil hidup
    -- panel ini tidak mendaftarkan satu pun, jadi tidak punya URL login atau
    registrasi sendiri. URL masuk sebagai props dari WelcomePanelProvider supaya
    template ini tidak perlu tahu nama route.

    Kenapa tamu dapat IKON + DROPDOWN, bukan tombol teks "Sign In":
      - Topbar ini sudah penuh (kotak pencarian, switcher bahasa, switcher tema),
        dan "Sign In" hanya memakai satu baris penuh untuk satu aksi. Ikon user
        adalah affordance yang sudah dikenal: "akun saya ada di sini".
      - Ikonnya langsung berarti "akun", jadi label yang panjangnya pindah ke
        dalam dropdown, di tempat ada ruang. Labelnya tetap dua bahasa lewat
        __(): id "Masuk ke Akun", en "Sign In To Account" -- sama dengan CTA di
        halaman /user/auth, jadi dua tempat itu memakai bahasa yang sama.
      - Dropdown, bukan avatar: avatar butuh user yang sudah login, sedangkan
        tamu memang belum punya. Ikon user-circle + satu item persis yang
        kurang untuk tamu.

    Ikon diambil dari registry Filament (panels::user-menu.profile-item) dengan
    fallback, jadi mengikuti ikon yang dipakai Filament sendiri dan tidak
    mengunci nama ikon ke dalam file ini.

    Rendered from the 'panels::global-search.after' hook rather than
    USER_MENU_BEFORE, because Filament only renders the user menu (and with it
    any hook inside it) once `filament()->auth()->check()` passes. Guests are
    the whole reason this control exists.

    The theme switcher comes along because Filament keeps it inside the user
    menu, which this panel does not render. It is a dropdown rather than
    Filament's stock three-button strip: next to the account control the strip
    crowded the topbar, and on a phone it wrapped onto a second row. The stock
    <x-filament-panels::theme-switcher /> has no dropdown variant to reuse, so
    this is hand-rolled from Filament's own dropdown parts, which is what keeps
    the panel, rows and transitions looking native. Dropdown tamu di atas juga memakai
    parts yang sama, dengan `teleport` supaya tidak terpotong topbar.

    Picking a row assigns `theme`. The wrapper's $watch turns that into the
    'theme-changed' event, and that event is the whole contract with the rest of
    the app -- it writes localStorage.theme and flips the `dark` class. This file
    never touches storage itself, exactly like the stock switcher.

    The trigger's three icons live in <template x-if> rather than x-show: a
    template's contents are not in the document until Alpine clones them, so
    there is no pre-Alpine frame showing every icon at once (this project has no
    global [x-cloak] rule to lean on instead).

    Which row is active is carried by the row tint alone. A tick beside the
    label was tried and cut: at this size the extra glyph became the loudest
    thing in a menu that only holds three entries.

    The active row is tinted with `x-bind:class`, not Blade's `:class`. On a
    Blade component `:class` is a PHP attribute binding, so it would evaluate
    `theme` as a constant and throw; `x-bind:class` rides along as a plain
    attribute for Alpine to bind.

    For the same reason the mode is interpolated as '{{ $theme }}' rather than
    @js($theme): Blade expands attributes on a component into a PHP array, and a
    directive inside one of those values is copied through untouched, so @js
    would reach the browser as the literal text "@js($theme)" and every row
    would throw on click. Plain interpolation is safe regardless -- the only
    possible values are the three keys of $themeOptions above.
--}}
@php
    use App\Support\AppPlatform\AppPlatform;

    // Ikon user/profile milik Filament, diambil dari registry-nya (ikon yang
    // sama dengan yang dipakai user menu bawaan) dengan fallback kalau paket
    // tidak mendaftarkan.
    $accountIcon = \Filament\Support\Facades\FilamentIcon::resolve('panels::user-menu.profile-item') ?? 'heroicon-m-user-circle';

    // Kelas tombolnya satu blok, dipakai kedua status (link dan trigger
    // dropdown), supaya ikon di topbar ini tidak melompat ukurannya kalau status
    // auth berubah (tamu <-> sudah login).
    //
    // TIDAK ada `ring-1` di sini. Ikon akun adalah satu-satunya kontrol topbar
    // yang tidak punya bentuk -- tidak ada kotak pencarian di sekitarnya yang
    // perlu dibedakan -- jadi garis di sekitarnya cuma menggambar kotak kosong
    // dengan isi di dalamnya, dan langsung terlihat terpisah di dalam kapsul
    // topbar yang sudah berbingkai. Dihapus; `rounded-md` + hover:bg tetap
    // dipertahankan supaya area kliknya tetap terbaca saat hover.
    //
    // `h-10 min-w-10` TETAP, tidak ikut membesar. Tinggi baris topbar
    // ditentukan oleh kotak pencarian Global Search (juga h-10), jadi kalau
    // tombol ikut jadi h-12, ikon akun akan lebih tinggi dari kotak di
    // sebelahnya dan barisnya terlihat meleset. Yang diperbesar hanya isi
    // ikonnya, dari h-5 (20px, setara icon-button Filament size "md") jadi
    // h-7 (28px) --masih di dalam kotak 40px, jadi sisanya 6px per sisi
    // dan area kliknya tidak berubah.
    $accountButtonClass = 'fi-welcome-auth-actions-account flex items-center justify-center h-10 min-w-10 rounded-md text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 focus-visible:outline-none focus-visible:bg-gray-50 dark:focus-visible:bg-white/5 transition-all active:scale-95';

    // Ukuran ikon akun. Dipisah dari kelas tombol supaya kedua status ikon
    // (link sudah login / tombol dropdown tamu) memakai ukuran yang sama --
    // kalau hanya salah satu yang berubah, ikon akan melompat saat status auth
    // berganti.
    $accountIconClass = 'h-7 w-7';

    // Switcher tema di topbar: hanya untuk tablet dan website desktop.
    //
    // Syaratnya AppPlatform::switchersBelongInTopbar() -- kebalikan persis
    // dari hook SIDEBAR_NAV_START di WelcomePanelProvider, yang menaruh
    // switcher yang sama di menu geser. Dua-duanya menanyakan predikat yang
    // sama, jadi switcher tema tidak mungkin muncul di topbar DAN sidebar
    // pada satu permukaan.
    //
    // Sebelumnya syaratnya hanya mengecek desktop app, sehingga di HP
    // markup-nya tetap dirender lalu disembunyikan `hidden sm:block` --
    // satu kontrol ada dua kali di DOM. Sekarang di sana tidak dirender
    // sama sekali.
    //
    // Diperiksa di PHP, bukan lewat CSS, supaya markup-nya benar-benar tidak
    // ada -- bukan "tersembunyi tapi tetap ada di DOM".
    //
    // PANEL LAIN (user, admin) TIDAK ikut: mereka tidak memakai partial ini.
    $showThemeSwitcher = AppPlatform::switchersBelongInTopbar();
@endphp
{{--
    URUTAN DI DALAM WRAPPER INI PENTING: switcher tema dulu, ikon akun
    TERAKHIR.

    Ikon akun adalah kontrol paling personal di panel ini -- dia yang
    melanjutkan ke sesi orang itu -- jadi posisinya di pojok kanan paling
    ujung, seperti di panel Filament pada umumnya. themedahulu karena begitu
    urutannya, switcher tema justru mengambil tempat terakhir dan ikon akun
    berhenti di tengah baris, padahal itu bukan tempatnya.

    Perhatikan juga bahwa hook bahasa (GLOBAL_SEARCH_AFTER di
    WelcomePanelProvider) dirender sebelum wrapper ini, jadi urutan penuh di
    pojok kanan adalah: pencarian -> bahasa -> tema -> ikon akun.
--}}
<div class="fi-welcome-auth-actions flex items-center gap-2 lg:gap-3">
    @if ($showThemeSwitcher)
        <div class="hidden sm:block">
            @include('Shared.components.theme-switcher.theme-switcher')
        </div>
    @endif

    @auth
        {{--
            Sudah login: ikon itu sendiri yang jadi link ke /user/home. Tidak ada
            dropdown dan tidak ada tombol "Beranda" -- satu tujuan, satu kontrol.
            Label "Beranda" tetap dipakai untuk aria-label + tooltip supaya
            maksud ikon terbaca screen reader dan saat hover.
        --}}
        <a href="{{ $homeUrl }}"
            aria-label="{{ __('Beranda') }}"
            x-tooltip="{
                content: @js(__('Beranda')),
                theme: $store.theme,
            }"
            class="{{ $accountButtonClass }}"
        >
            <x-filament::icon :icon="$accountIcon" :class="$accountIconClass" />
        </a>
    @else
        {{--
            Tamu: ikon user (bawaan Filament) + dropdown berisi satu aksi,
            "Sign In To Account" / "Masuk ke Akun". Dropdown-nya disusun dari
            parts yang sama dengan theme switcher, jadi keduanya native;
            `teleport` supaya tidak terpotong baris topbar.
        --}}
        <x-filament::dropdown placement="bottom-end" teleport>
            <x-slot name="trigger">
                <button
                    type="button"
                    aria-label="{{ __('Sign In To Account') }}"
                    x-tooltip="{
                        content: @js(__('Sign In To Account')),
                        theme: $store.theme,
                    }"
                    class="{{ $accountButtonClass }}"
                >
                    <x-filament::icon :icon="$accountIcon" :class="$accountIconClass" />
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="$loginUrl"
                    :icon="$accountIcon"
                >
                    {{ __('Sign In To Account') }}
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endauth
</div>
