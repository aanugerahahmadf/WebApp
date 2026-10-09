{{--
    Cookie Consent Banner (GDPR/ePrivacy compliant).
    
    Tampilkan di bagian bawah layar untuk guest dan user.
    Tiga pilihan:
    - Terima Semua (analytics, marketing, personalization)
    - Hanya Wajib (essential cookies only)
    - Tolak Non-Essential (reject analytics/marketing)
    
    State disimpan di cookie 'cookie_consent' (1 tahun).
    JavaScript di bawah handle interaksi tanpa reload halaman.
--}}

@php
    $consent = request()->cookie('cookie_consent');
    $showBanner = empty($consent) || $consent === 'not_set';
@endphp

@if ($showBanner)
<div id="cookie-consent-banner"
     class="fixed bottom-0 left-0 right-0 z-50 transform transition-transform duration-300 ease-out"
     x-data="{
         open: true,
         animateIn: false,
         acceptAll() { this.setConsent('accepted'); },
         essentialOnly() { this.setConsent('essential_only'); },
         rejectNonEssential() { this.setConsent('rejected'); },
         async setConsent(value) {
             this.animateIn = false;
             try {
                 const res = await fetch('/cookie-consent/' + value, {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                     },
                 });
                 if (res.ok) {
                     this.open = false;
                     // Trigger event untuk scripts yang perlu tahu consent berubah
                     window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: value } }));
                 }
             } catch (e) {
                 console.error('Cookie consent error:', e);
                 this.animateIn = true; // Tampilkan lagi kalau error
             }
         },
         init() {
             this.$nextTick(() => { this.animateIn = true; });
         }
     }"
     x-show="open"
     x-transition:enter="transition-transform ease-out duration-300"
     x-transition:enter-start="translate-y-full opacity-0"
     x-transition:enter-end="translate-y-0 opacity-100"
     x-transition:leave="transition-transform ease-in duration-200"
     x-transition:leave-start="translate-y-0 opacity-100"
     x-transition:leave-end="translate-y-full opacity-0"
     x-cloak
     :class="animateIn ? '' : 'translate-y-full opacity-0'"
     role="dialog"
     aria-label="{{ __('Cookie Consent') }}"
     aria-describedby="cookie-consent-description"
>
    <div class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 py-4">
                <!-- Icon & Title -->
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="flex-shrink-0 mt-0.5 text-gray-500 dark:text-gray-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('Kami Menggunakan Cookie') }}
                        </h3>
                        <p id="cookie-consent-description" class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('Kami menggunakan cookie untuk meningkatkan pengalaman Anda, menganalisis traffic, dan mempersonalisasi konten. Dengan menekan \"Terima Semua\", Anda menyetujui penggunaan semua cookie.') }}
                        </p>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <!-- Essential Only -->
                    <button type="button"
                            @click="essentialOnly()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
                            aria-label="{{ __('Hanya cookie wajib') }}">
                        {{ __('Hanya Wajib') }}
                    </button>

                    <!-- Reject Non-Essential -->
                    <button type="button"
                            @click="rejectNonEssential()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
                            aria-label="{{ __('Tolak cookie non-wajib') }}">
                        {{ __('Tolak') }}
                    </button>

                    <!-- Accept All -->
                    <button type="button"
                            @click="acceptAll()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
                            aria-label="{{ __('Terima semua cookie') }}">
                        {{ __('Terima Semua') }}
                    </button>
                </div>
            </div>

            <!-- Detail link -->
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    {{ __('Kelola preferensi kapan saja dari') }}
                    <a href="{{ url('/cookie-preferences') }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                        {{ __('Pengaturan Cookie') }}
                    </a>
                    {{ __('atau baca') }}
                    <a href="{{ url('/privacy-policy') }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                        {{ __('Kebijakan Privasi') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Styles untuk banner --}}
<style>
    /* Pastikan banner tidak menutupi content di mobile */
    @media (max-width: 640px) {
        #cookie-consent-banner {
            border-radius: 0;
        }
    }
    
    /* Animasi masuk/keluar yang smooth */
    #cookie-consent-banner[x-cloak] {
        display: none !important;
    }
</style>

{{-- Script untuk handle CSRF token --}}
@push('scripts')
<script>
    // Pastikan CSRF token tersedia untuk fetch
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.querySelector('meta[name="csrf-token"]')) {
            const token = '{{ csrf_token() }}';
            const meta = document.createElement('meta');
            meta.name = 'csrf-token';
            meta.content = token;
            document.head.appendChild(meta);
        }
    });
</script>
@endpush