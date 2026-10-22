{{--
    MODAL BAGIKAN KONTAK untuk halaman Messages.

    Dipakai oleh tiga panel sekaligus (Admin, User, Welcome) lewat satu file.
    Dibuka lewat event window `messages-contact-open` dari item menu lampiran.

    Yang dikirim adalah berkas vCard (.vcf) yang dihasilkan di sisi browser, lalu
    diserahkan ke `window.messagesAttachFile()` supaya ikut alur lampiran yang
    sama dengan kamera dan audio.

    FIELD TELEPON memakai gaya WhatsApp: pemilih kode negara di sebelah kiri,
    lalu nomor nasional di sebelah kanan. Nomor akhirnya dirangkai jadi E.164
    (mis. +6281234567890) sebelum ditulis ke vCard, supaya kartu kontaknya
    bisa langsung dibuka di ponsel.

    PEMILIH NEGARA TIDAK memakai <select> native.
    Alasannya: daftar negara ini berisi 245 entri, dan dropdown native
    Chromium akan membuka panel setinggi layar yang menimpa seluruh modal
    (terlihat sebagai kotak putih raksasa). Karena itu dibuat dropdown
    sendiri: tombol kecil berisi kode ISO + kode dial, dan panel berisi
    daftar yang bisa dicari.

    Kode ISO dipakai, bukan bendera emoji: Chromium di Windows tidak punya
    glyph bendera negara, jadi emoji-nya tampil sebagai dua huruf biasa
    (mis. "KG") dan bukan bendera. Daftar negara diambil dari
    `App\Support\Phone\CountryCallingCodeOptions` -- sumber yang sama dengan
    field "Nomor WhatsApp" di form profil, jadi satu negara punya satu
    daftar saja di seluruh aplikasi.

    PENTING -- nama state berawalan `contact`.
    Semua state di sini berawalan `contact` supaya tidak bentrok dengan state
    `open` milik menu lampiran atau komponen lain di halaman yang sama.
--}}
@php
    $countryRows = \App\Support\Phone\CountryCallingCodeOptions::list();
    $defaultCountry = \App\Support\Phone\CountryCallingCodeOptions::defaultSelection();
@endphp

<div
    x-data="{
        contactOpen: false,
        contactName: '',
        contactCountry: '{{ $defaultCountry }}',
        contactPhone: '',
        contactEmail: '',
        contactError: null,

        contactCountryOpen: false,
        contactCountryQuery: '',
        contactCountryCache: null,

        contactOpenModal() {
            this.contactError = null;
            this.contactOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        contactClose() {
            this.contactOpen = false;
            this.contactCountryOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        /* Daftar negara diambil sekali dari blok JSON di bawah. */
        contactCountries() {
            if (this.contactCountryCache) {
                return this.contactCountryCache;
            }

            const el = document.getElementById('messages-contact-countries');
            this.contactCountryCache = el ? JSON.parse(el.textContent) : [];

            return this.contactCountryCache;
        },

        contactCurrentCountry() {
            const countries = this.contactCountries();

            return countries.find((country) => country.value === this.contactCountry)
                || countries[0]
                || { value: '{{ $defaultCountry }}', iso: 'ID', name: 'Indonesia', dial: '+62' };
        },

        contactFilteredCountries() {
            const query = this.contactCountryQuery.trim().toLowerCase();

            if (query === '') {
                return this.contactCountries();
            }

            return this.contactCountries().filter((country) => {
                return country.name.toLowerCase().includes(query)
                    || country.dial.includes(query)
                    || country.iso.toLowerCase() === query;
            });
        },

        contactSelectCountry(value) {
            this.contactCountry = value;
            this.contactCountryOpen = false;
            this.contactCountryQuery = '';
        },

        /* Kode dial saja, mis. '+62' dari '+62|ID'. */
        contactDialCode() {
            return String(this.contactCountry || '').split('|')[0] || '+62';
        },

        /* Nomor nasional -> E.164, seperti WhatsApp: '0812...' jadi '+62812...'. */
        contactE164() {
            const digits = String(this.contactPhone || '').replace(/\D/g, '');

            if (digits === '') return '';

            const national = digits.indexOf('0') === 0 ? digits.slice(1) : digits;

            return this.contactDialCode() + national;
        },

        contactCanSend() {
            return this.contactName.trim() !== '' && this.contactE164() !== '';
        },

        contactEscape(value) {
            return String(value ?? '')
                .replace(/\\/g, '\\\\')
                .replace(/\r?\n/g, '\\n')
                .replace(/;/g, '\\;')
                .replace(/,/g, '\\,');
        },

        contactBuildVcard() {
            const lines = [
                'BEGIN:VCARD',
                'VERSION:3.0',
                'PRODID:-//WeddingFlowers//Messages//ID',
                'FN:' + this.contactEscape(this.contactName.trim()),
                'N:' + this.contactEscape(this.contactName.trim()) + ';;;;',
                'TEL;TYPE=CELL;VALUE=uri:tel:' + this.contactE164(),
            ];

            if (this.contactEmail.trim()) {
                lines.push('EMAIL;TYPE=INTERNET:' + this.contactEmail.trim());
            }

            lines.push('END:VCARD');

            return lines.join('\r\n');
        },

        contactSend() {
            if (!this.contactCanSend()) {
                this.contactError = @js(__('Nama dan nomor telepon wajib diisi.'));
                return;
            }

            const fileName = (this.contactName.trim() || 'contact').replace(/[^a-zA-Z0-9_\-]+/g, '-').replace(/^-+|-+$/g, '') || 'contact';
            const blob = new Blob([this.contactBuildVcard()], { type: 'text/vcard;charset=utf-8' });
            const file = new File([blob], fileName + '.vcf', { type: 'text/vcard' });

            if (window.messagesAttachFile && window.messagesAttachFile(file)) {
                this.contactName = '';
                this.contactPhone = '';
                this.contactEmail = '';
                this.contactError = null;
                this.contactClose();

                // Jembatan di dalam komponen percakapan yang mengunggah lalu
                // mengirim pesanannya, jadi gelembung pesannya langsung muncul.
            } else {
                this.contactError = @js(__('Lampiran tidak dapat disiapkan. Coba lagi.'));
            }
        },
    }"
    x-on:messages-contact-open.window="contactOpenModal()"
    x-on:keydown.escape.window="contactOpen && contactClose()"
    x-on:livewire:navigating.window="contactClose()"
    class="contents"
>
    {{-- Data negara. JSON_HEX_TAG supaya aman kalau ada karakter '<' di dalam data. --}}
    <script type="application/json" id="messages-contact-countries">@json($countryRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="contactOpen"
            x-on:click.self="contactClose()"
            class="fixed inset-0 z-[9998] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Contact') }}"
        >
            <div
                x-on:click.stop
                class="fi-glass-modal w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ __('Contact') }}</h3>

                    <button type="button" x-on:click="contactClose()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Tutup') }}">
                        <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-3 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Nama') }} <span class="text-danger-500">*</span>
                        <input
                            type="text"
                            x-model="contactName"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            placeholder="{{ __('Nama kontak') }}"
                        >
                    </label>

                    {{--
                        Telepon gaya WhatsApp: kode negara + nomor nasional.
                        Compose ke E.164 di contactE164() sebelum ditulis ke vCard.
                    --}}
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Nomor Telepon') }} <span class="text-danger-500">*</span>

                        <div class="mt-1 flex items-stretch">
                            {{-- Pemilih negara: dropdown custom, bukan <select> native. --}}
                            <div class="relative shrink-0">
                                <button
                                    type="button"
                                    x-on:click="contactCountryOpen = !contactCountryOpen"
                                    class="flex h-full items-center gap-1.5 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-2.5 text-sm text-gray-900 shadow-sm transition-colors hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                                    title="{{ __('Negara / Kode Negara') }}"
                                >
                                    <span
                                        class="grid h-5 min-w-[1.5rem] place-items-center rounded bg-gray-200 px-1 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300"
                                        x-text="contactCurrentCountry().iso"
                                    >ID</span>
                                    <span class="font-medium" x-text="contactCurrentCountry().dial">+62</span>
                                    <x-filament::icon icon="/images/icons/chevron_down.svg" class="h-3.5 w-3.5 text-gray-400" />
                                </button>

                                <div
                                    x-cloak
                                    x-show="contactCountryOpen"
                                    x-on:click.outside="contactCountryOpen = false"
                                    class="absolute left-0 top-full z-50 mt-1 w-72 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-800"
                                >
                                    <div class="border-b border-gray-100 p-2 dark:border-white/10">
                                        <input
                                            type="search"
                                            x-model="contactCountryQuery"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                            placeholder="{{ __('Cari negara...') }}"
                                        >
                                    </div>

                                    <div class="max-h-56 overflow-y-auto py-1">
                                        <template x-for="country in contactFilteredCountries()" :key="country.value">
                                            <button
                                                type="button"
                                                x-on:click="contactSelectCountry(country.value)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10"
                                            >
                                                <span
                                                    class="grid h-5 min-w-[1.5rem] place-items-center rounded bg-gray-200 px-1 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300"
                                                    x-text="country.iso"
                                                ></span>
                                                <span class="flex-1 truncate" x-text="country.name"></span>
                                                <span class="shrink-0 text-xs text-gray-400" x-text="country.dial"></span>
                                            </button>
                                        </template>

                                        <p
                                            x-show="contactFilteredCountries().length === 0"
                                            class="px-3 py-4 text-center text-sm text-gray-400"
                                        >{{ __('Negara tidak ditemukan.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <input
                                type="tel"
                                inputmode="tel"
                                x-model="contactPhone"
                                class="block w-full min-w-0 rounded-r-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                placeholder="{{ __('81234567890') }}"
                            >
                        </div>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Email') }}
                        <input
                            type="email"
                            x-model="contactEmail"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            placeholder="{{ __('nama@contoh.com') }}"
                        >
                    </label>

                    {{-- Pratinjau kartu kontak, sama seperti yang akan dikirim. --}}
                    <div x-show="contactName.trim() !== '' || contactE164() !== ''" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white text-primary-600 shadow-sm dark:bg-gray-900 dark:text-primary-400">
                            <x-filament::icon icon="/images/icons/user.svg" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="contactName.trim() || @js(__('Nama kontak'))">-</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="contactE164() || '-'">-</p>
                        </div>
                    </div>

                    <div x-show="contactError" class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300" x-text="contactError"></div>

                    <div class="flex justify-end gap-3 pt-1">
                        <x-filament::button color="gray" x-on:click="contactClose()">
                            {{ __('Batal') }}
                        </x-filament::button>

                        <x-filament::button color="primary" x-on:click="contactSend()" x-bind:disabled="!contactCanSend()">
                            {{ __('Kirim Kontak') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
