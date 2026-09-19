<div>
    <x-filament::section
        aside
        icon="heroicon-o-bell"
        :heading="__('Pengaturan Notifikasi')"
        :description="__('Atur kategori notifikasi yang ingin Anda terima.')"
    >
        <div
            x-data="notificationPrefs()"
            class="flex flex-col gap-4"
        >
            <div class="flex items-center justify-end gap-x-3">
                <x-filament::button
                    x-on:click="toggleAll(false)"
                    color="gray"
                    size="sm"
                >
                    {{ __('Nonaktifkan Semua') }}
                </x-filament::button>
                <x-filament::button
                    x-on:click="toggleAll(true)"
                    color="primary"
                    size="sm"
                >
                    {{ __('Aktifkan Semua') }}
                </x-filament::button>
            </div>

            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-m-speaker-wave"
                    class="h-5 w-5 text-primary-500"
                />
                <div class="flex flex-col">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Suara Notifikasi') }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Putar suara saat notifikasi masuk') }}</span>
                </div>
                <button
                    x-on:click="toggle('sound')"
                    x-bind:class="prefs['sound'] ? 'bg-primary-500' : 'bg-gray-200 dark:bg-white/10'"
                    class="relative ml-auto inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                    role="switch"
                    x-bind:aria-checked="prefs['sound']"
                >
                    <span
                        x-bind:class="prefs['sound'] ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    />
                </button>
            </div>

            <template x-for="category in categories" :key="category.key">
                <div class="flex items-center gap-3">
                    <x-filament::icon
                        x-bind:icon="category.icon"
                        x-bind:class="prefs[category.key] ? 'text-primary-500' : 'text-gray-400 dark:text-gray-500'"
                        class="h-5 w-5"
                    />
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-950 dark:text-white" x-text="category.label"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="category.description"></span>
                    </div>
                    <button
                        x-on:click="toggle(category.key)"
                        x-bind:class="prefs[category.key] ? 'bg-primary-500' : 'bg-gray-200 dark:bg-white/10'"
                        class="relative ml-auto inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                        role="switch"
                        x-bind:aria-checked="prefs[category.key]"
                    >
                        <span
                            x-bind:class="prefs[category.key] ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        />
                    </button>
                </div>
            </template>
        </div>
    </x-filament::section>

    @once
        @push('scripts')
            <script>
                function notificationPrefs() {
                    const storageKey = 'notification_prefs';
                    const defaults = {
                        'sound': true,
                        'messages': true,
                        'products': true,
                        'packages': true,
                        'vouchers': true,
                        'orders': true,
                        'wishlist': true,
                        'reviews': true,
                        'security': true,
                    };
                    const categories = [
                        { key: 'messages', icon: 'heroicon-m-chat-bubble-left-right', label: {{ json_encode(__('Notifikasi Chat')) }}, description: {{ json_encode(__('Terima notifikasi pesan baru')) }} },
                        { key: 'products', icon: 'heroicon-m-shopping-bag', label: {{ json_encode(__('Notifikasi Produk')) }}, description: {{ json_encode(__('Terima info produk baru dan produk unggulan')) }} },
                        { key: 'packages', icon: 'heroicon-m-cube', label: {{ json_encode(__('Notifikasi Paket')) }}, description: {{ json_encode(__('Terima info paket pernikahan yang tersedia')) }} },
                        { key: 'vouchers', icon: 'heroicon-m-ticket', label: {{ json_encode(__('Notifikasi Voucher & Promo')) }}, description: {{ json_encode(__('Terima notifikasi voucher, promo dan diskon')) }} },
                        { key: 'orders', icon: 'heroicon-m-receipt-refund', label: {{ json_encode(__('Notifikasi Pesanan')) }}, description: {{ json_encode(__('Terima notifikasi status pesanan')) }} },
                        { key: 'wishlist', icon: 'heroicon-m-heart', label: {{ json_encode(__('Notifikasi Favorit')) }}, description: {{ json_encode(__('Terima notifikasi wishlist')) }} },
                        { key: 'reviews', icon: 'heroicon-m-star', label: {{ json_encode(__('Notifikasi Ulasan')) }}, description: {{ json_encode(__('Terima notifikasi tentang ulasan dan penilaian')) }} },
                        { key: 'security', icon: 'heroicon-m-shield-check', label: {{ json_encode(__('Notifikasi Keamanan')) }}, description: {{ json_encode(__('Terima notifikasi login dan keamanan akun')) }} },
                    ];
                    return {
                        prefs: Object.assign({}, defaults),
                        categories,
                        init() {
                            try {
                                const saved = JSON.parse(localStorage.getItem(storageKey));
                                if (saved) {
                                    this.prefs = Object.assign({}, defaults, saved);
                                }
                            } catch (e) {
                                this.prefs = Object.assign({}, defaults);
                            }
                        },
                        save() {
                            try {
                                localStorage.setItem(storageKey, JSON.stringify(this.prefs));
                            } catch (e) {}
                        },
                        toggle(key) {
                            this.prefs[key] = !this.prefs[key];
                            this.save();
                        },
                        toggleAll(value) {
                            Object.keys(this.prefs).forEach((k) => {
                                this.prefs[k] = value;
                            });
                            this.save();
                        },
                    };
                }
            </script>
        @endpush
    @endonce
</div>