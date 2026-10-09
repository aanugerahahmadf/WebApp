{{--
    Cookie Preferences Page.
    User bisa ubah preferensi cookie kapan saja.
--}}

<x-filament-panels::page>
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ __('Pengaturan Cookie') }}
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
                {{ __('Kelola preferensi cookie Anda di bawah ini. Perubahan berlaku segera.') }}
            </p>
        </div>

        <div x-data="{
            consent: '{{ request()->cookie('cookie_consent', 'not_set') }}',
            saving: false,
            async updateConsent(value) {
                this.saving = true;
                try {
                    const res = await fetch('/cookie-consent/' + value, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                        },
                    });
                    if (res.ok) {
                        this.consent = value;
                        this.$dispatch('notify', {
                            status: 'success',
                            title: '{{ __('Berhasil') }}',
                            body: value === 'accepted' ? '{{ __('Semua cookie diterima.') }}' : 
                                  value === 'essential_only' ? '{{ __('Hanya cookie wajib yang diterima.') }}' :
                                  '{{ __('Cookie non-wajib ditolak.') }}',
                        });
                        window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: value } }));
                    }
                } catch (e) {
                    console.error('Cookie consent error:', e);
                    this.$dispatch('notify', {
                        status: 'danger',
                        title: '{{ __('Gagal') }}',
                        body: '{{ __('Gagal menyimpan preferensi. Silakan coba lagi.') }}',
                    });
                } finally {
                    this.saving = false;
                }
            },
            async resetConsent() {
                this.saving = true;
                try {
                    const res = await fetch('/cookie-consent/reset', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                        },
                    });
                    if (res.ok) {
                        this.consent = 'not_set';
                        this.$dispatch('notify', {
                            status: 'success',
                            title: '{{ __('Direset') }}',
                            body: '{{ __('Preferensi cookie direset. Banner akan muncul lagi.') }}',
                        });
                        window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: 'not_set' } }));
                    }
                } catch (e) {
                    console.error('Cookie consent error:', e);
                } finally {
                    this.saving = false;
                }
            }
        }">
            <!-- Current Status -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ __('Status Saat Ini') }}
                </h2>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Status') }}</div>
                        <div class="text-lg font-medium" x-text="{
                            'accepted': '{{ __('Semua Cookie Diterima') }}',
                            'essential_only': '{{ __('Hanya Cookie Wajib') }}',
                            'rejected': '{{ __('Non-Wajib Ditolak') }}',
                            'not_set': '{{ __('Belum Diatur') }}',
                        }[consent] || consent"></div>
                    </div>
                    <x-filament::badge :color="{
                        'accepted': 'success',
                        'essential_only': 'warning',
                        'rejected': 'danger',
                        'not_set': 'gray',
                    }[consent] || 'gray'" size="lg" x-text="{
                        'accepted': '{{ __('Semua') }}',
                        'essential_only': '{{ __('Wajib Saja') }}',
                        'rejected': '{{ __('Ditolak') }}',
                        'not_set': '{{ __('Belum') }}',
                    }[consent] || consent"></x-filament::badge>
                </div>
            </div>

            <!-- Cookie Categories -->
            <div class="space-y-4">
                <template x-for="category in categories" :key="category.key">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white" x-text="category.label"></h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="category.description"></p>
                                <div class="mt-2 flex flex-wrap gap-1" x-show="category.cookies.length">
                                    <template x-for="cookie in category.cookies" :key="cookie">
                                        <span class="inline-block px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded" x-text="cookie"></span>
                                    </template>
                                </div>
                            </div>
                            <div class="flex-shrink-0" x-show="!category.required">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           class="sr-only peer"
                                           :checked="isCategoryEnabled(category.key)"
                                           @change="toggleCategory(category.key)">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                </label>
                            </div>
                            <div class="flex-shrink-0 text-sm text-gray-500 dark:text-gray-400" x-show="category.required">
                                {{ __('Wajib') }}
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <x-filament::button type="button"
                                    @click="updateConsent('accepted')"
                                    :disabled="saving || consent === 'accepted'"
                                    color="primary"
                                    class="w-full sm:w-auto">
                    {{ __('Terima Semua') }}
                </x-filament::button>

                <x-filament::button type="button"
                                    @click="updateConsent('essential_only')"
                                    :disabled="saving || consent === 'essential_only'"
                                    color="gray"
                                    class="w-full sm:w-auto">
                    {{ __('Hanya Wajib') }}
                </x-filament::button>

                <x-filament::button type="button"
                                    @click="updateConsent('rejected')"
                                    :disabled="saving || consent === 'rejected'"
                                    color="danger"
                                    outlined
                                    class="w-full sm:w-auto">
                    {{ __('Tolak Non-Wajib') }}
                </x-filament::button>

                <x-filament::button type="button"
                                    @click="resetConsent"
                                    :disabled="saving || consent === 'not_set'"
                                    color="gray"
                                    outlined
                                    class="w-full sm:w-auto">
                    {{ __('Reset & Tampilkan Banner') }}
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>

@php
    $categories = [
        [
            'key' => 'essential',
            'label' => __('Cookie Wajib (Essential)'),
            'description' => __('Cookie ini diperlukan agar situs web berfungsi dengan benar. Tidak dapat dinonaktifkan.'),
            'required' => true,
            'cookies' => ['session', 'csrf_token', 'cookie_consent', 'XSRF-TOKEN'],
        ],
        [
            'key' => 'analytics',
            'label' => __('Cookie Analitik'),
            'description' => __('Memahami bagaimana pengunjung berinteraksi dengan situs untuk meningkatkan performa.'),
            'required' => false,
            'cookies' => ['_ga', '_gid', '_gat', '__utma', '__utmb'],
        ],
        [
            'key' => 'marketing',
            'label' => __('Cookie Pemasaran'),
            'description' => __('Menampilkan iklan yang relevan berdasarkan minat Anda.'),
            'required' => false,
            'cookies' => ['_fbp', '_gcl_au', 'IDE', 'ANID'],
        ],
        [
            'key' => 'preferences',
            'label' => __('Cookie Preferensi'),
            'description' => __('Mengingat pengaturan Anda (bahasa, tema, region) untuk pengalaman yang lebih personal.'),
            'required' => false,
            'cookies' => ['locale', 'theme', 'currency'],
        ],
    ];
@endphp

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cookiePreferences', () => ({
            categories: @json($categories),
            consent: '{{ request()->cookie('cookie_consent', 'not_set') }}',
            saving: false,
            
            isCategoryEnabled(key) {
                if (this.consent === 'accepted') return true;
                if (this.consent === 'essential_only') return key === 'essential';
                if (this.consent === 'rejected') return key === 'essential';
                return key === 'essential';
            },
            
            toggleCategory(key) {
                // Toggle logic handled by the main buttons
            },
            
            async updateConsent(value) {
                this.saving = true;
                try {
                    const res = await fetch('/cookie-consent/' + value, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    if (res.ok) {
                        this.consent = value;
                        this.$dispatch('notify', {
                            status: 'success',
                            title: '{{ __('Berhasil') }}',
                            body: value === 'accepted' ? '{{ __('Semua cookie diterima.') }}' : 
                                  value === 'essential_only' ? '{{ __('Hanya cookie wajib yang diterima.') }}' :
                                  '{{ __('Cookie non-wajib ditolak.') }}',
                        });
                        window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: value } }));
                    }
                } catch (e) {
                    console.error('Cookie consent error:', e);
                    this.$dispatch('notify', {
                        status: 'danger',
                        title: '{{ __('Gagal') }}',
                        body: '{{ __('Gagal menyimpan preferensi. Silakan coba lagi.') }}',
                    });
                } finally {
                    this.saving = false;
                }
            },
            
            async resetConsent() {
                this.saving = true;
                try {
                    const res = await fetch('/cookie-consent/reset', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    if (res.ok) {
                        this.consent = 'not_set';
                        this.$dispatch('notify', {
                            status: 'success',
                            title: '{{ __('Direset') }}',
                            body: '{{ __('Preferensi cookie direset. Banner akan muncul lagi.') }}',
                        });
                        window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: 'not_set' } }));
                    }
                } catch (e) {
                    console.error('Cookie consent error:', e);
                } finally {
                    this.saving = false;
                }
            }
        }));
    });
</script>