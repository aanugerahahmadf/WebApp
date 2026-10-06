{{--
    OtpEmailOrTwoFactory -- satu halaman untuk tiga cara verifikasi setelah
    Sign In (kode email, aplikasi autentikasi, kode pemulihan).

    Susunan mengikuti gambar acuan:

      keadaan "terpilih":
        judul + keterangan + isian kode + tombol Verifikasi + "Opsi Lainnya"

      keadaan "menu":
        judul + keterangan + "Opsi Lainnya" (buka) + garis + daftar pilihan

    "Opsi Lainnya" hanya x-show, jadi Filament tetap mengirim ulang isian
    kode ke server saat open/close -- tidak ada state Alpine yang bisa
    Featuring kolotak dengan data OTP.
--}}
<x-filament-panels::page.simple>
    <div class="flex flex-col items-center gap-6">

        {{-- Logo / lencana brand, seperti di gambar acuan --}}
        <div class="flex size-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-sm">
            <x-filament::icon
                icon="heroicon-o-lock-closed"
                class="size-7"
            />
        </div>

        <form wire:submit="save" class="w-full max-w-sm">

            {{-- Isian kode --}}
            <div class="flex flex-col gap-4">
                <x-filament::input.wrapper class="w-full">
                    <input
                        type="text"
                        wire:model="data.otp"
                        placeholder="{{ $method === 'recovery' ? 'XXXX-XXXX' : 'XXXXXX' }}"
                        inputmode="{{ $method === 'recovery' ? 'text' : 'numeric' }}"
                        autocomplete="one-time-code"
                        autocapitalize="characters"
                        spellcheck="false"
                        maxlength="9"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-center text-lg tracking-widest text-gray-950 focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </x-filament::input.wrapper>

                <x-filament::button
                    type="submit"
                    color="success"
                    size="lg"
                    class="w-full justify-center"
                >
                    {{ __('Verifikasi') }}
                </x-filament::button>
            </div>

            {{--
                Opsi Lainnya.
                opens: caret ke atas, daftar Inline tampil.
                tertutup: caret ke bawah, disembunyikan.
            --}}
            <div class="mt-4" x-data="{ open: @js($moreOpen) }">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                >
                    <span>{{ __('Opsi Lainnya') }}</span>
                    <x-filament::icon
                        icon="heroicon-m-chevron-up"
                        class="size-4 transition-transform"
                        x-bind:class="open ? '' : 'rotate-180'"
                    />
                </button>

                <div x-show="open" x-cloak class="mt-4 flex flex-col gap-2 border-t border-gray-200 pt-4 dark:border-white/10">
                    @if ($method === 'email')
                        <button
                            type="button"
                            wire:click="resendEmailCode"
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            {{ __('Kirim ulang kode') }}
                        </button>
                    @endif

                    @if ($method !== 'authenticator')
                        <button
                            type="button"
                            wire:click="useMethod('authenticator')"
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            {{ __('Aplikasi Autentikasi') }}
                        </button>
                    @endif

                    @if ($method !== 'recovery')
                        <button
                            type="button"
                            wire:click="useMethod('recovery')"
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            {{ __('Kode Pemulihan 2FA') }}
                        </button>
                    @endif

                    @if ($method !== 'email')
                        <button
                            type="button"
                            wire:click="useMethod('email')"
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            {{ __('Kirim Kode ke Email') }}
                        </button>
                    @endif

                    {{-- Reset device: satu-satunya jalan keluar kalau terkunci --}}
                    <button
                        type="button"
                        wire:click="logout"
                        wire:confirm="{{ __('Keluar dari akun ini dan Sign In ulang?') }}"
                        class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm font-semibold text-danger-600 transition hover:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                    >
                        {{ __('Mulai Pemulihan Akun') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page.simple>