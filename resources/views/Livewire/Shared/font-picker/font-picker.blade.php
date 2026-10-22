{{--
    Pemilih huruf (font picker).

    Isinya satu TOMBOL (action Filament) yang membuka modal berisi grid
    huruf -- BUKAN dropdown select: daftarnya 246+ keluarga, jadi isinya
    harus bisa dilihat bentuknya, bukan cuma dibaca.

    Letaknya di halaman Pengaturan (storefront & admin), bukan di topbar:
    huruf itu infrequently changed, dan topbar sudah padat.

    Muat ulang halaman setelah pilihan diterapkan: huruf baru ditulis ke
    CSS server-side (`--app-font-family` di AppFont::styles()), jadi tanpa
    reload layarnya masih huruf yang lama.

    Delay 700ms biar notifikasi "huruf berhasil diubah" sempat terlihat
    sebelum halaman dimuat ulang.
--}}
<div
    x-data
    x-on:font-picker-applied.window="setTimeout(() => window.location.reload(), 700)"
    data-font-picker-target="{{ $target }}"
    class="fi-font-picker"
>
    <x-filament::section
        :heading="$this->heading()"
        :description="$this->description()"
    >
        <div class="fi-font-picker-body flex flex-wrap items-center gap-3">
            {{-- Pratinjau huruf yang sedang aktif. --}}
            <span
                class="fi-font-picker-current rounded-md bg-gray-100 dark:bg-white/5 px-3 py-1.5 text-base"
                style="font-family: '{{ $this->currentFamily() }}' !important;"
            >
                {{ $this->currentFamily() }}
            </span>

            {{-- Action: membuka modal grid huruf. --}}
            {{ $this->pickFontAction }}

            {{--
                Kembalikan ke bawaan. Hanya untuk target 'viewer' (pilihan
                pribadi): admin sudah punya tombolnya sendiri di form
                appearance, dan di sini "bawaan" = pilihan global admin.
            --}}
            @if ($this->target === 'viewer')
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    wire:click="resetFont"
                >
                    {{ __('Kembalikan ke Bawaan') }}
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    {{-- Modal action (grid huruf) hidup di dalam komponen ini. --}}
    <x-filament-actions::modals />
</div>
