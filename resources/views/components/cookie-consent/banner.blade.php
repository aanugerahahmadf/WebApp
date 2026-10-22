{{--
    Cookie Consent Banner (GDPR/ePrivacy compliant).

    Tampilkan sebagai OVERLAY mengambang di bawah layar, meniru gaya
    kapsul Top Navigation: kaca buram, border tipis, sudut bulat.

    PENTING -- kenapa kelas CSS, bukan utilitas Tailwind:
    utilitas responsif (sm:*, lg:*) tidak ikut ter-build untuk blade ini,
    sehingga `sm:w-auto` dsb. tidak berefek. Akibatnya tombol tetap
    memakai w-full dan memakan seluruh lebar, kolom teks menyempit
    jadi 0, dan tiap kata turun satu baris. SEMUA gaya dan tata letak
    banner karena itu ditulis sebagai CSS biasa (kelas .cc-*) di
    public/css/panel-glass.css -- file itu dimuat lewat <link>, jadi
    berlaku seketika setelah refresh tanpa `npm run build`.

    Gaya kaca (sesuai permintaan):
        Night : blur 15px, tint Dark 50  -> rgb(0 0 0 / 0.50)
        Light : blur 15px, tint Light 50 -> rgb(255 255 255 / 0.50)

    State disimpan di cookie 'cookie_consent' (1 tahun).
    JavaScript di bawah handle interaksi tanpa reload halaman.
--}}

@php
    $consent = request()->cookie('cookie_consent');
    $showBanner = empty($consent) || $consent === 'not_set';
@endphp

@if ($showBanner)
<script>
    function cookieConsentBanner() {
        return {
            open: true,
            acceptAll() { this.setConsent('accepted'); },
            essentialOnly() { this.setConsent('essential_only'); },
            rejectNonEssential() { this.setConsent('rejected'); },
            async setConsent(value) {
                this.open = false;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    await fetch('/cookie-consent/' + value, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { consent: value } }));
                } catch (e) {
                    console.error('Cookie consent error:', e);
                    this.open = true;
                }
            }
        };
    }
</script>

<div id="cookie-consent-banner"
     x-data="cookieConsentBanner()"
     x-show="open"
     x-cloak
     x-transition:enter="cc-fade-enter"
     x-transition:enter-start="cc-fade-enter-start"
     x-transition:enter-end="cc-fade-enter-end"
     x-transition:leave="cc-fade-leave"
     x-transition:leave-start="cc-fade-leave-start"
     x-transition:leave-end="cc-fade-leave-end"
     role="dialog"
     aria-label="{{ __('Cookie Consent') }}"
     aria-describedby="cookie-consent-description">

    <div class="cc-bar">
        <div class="cc-main">
            <!-- Icon -->
            <span class="cc-icon" aria-hidden="true">
                <x-filament::icon icon="/images/icons/lock.svg" />
            </span>

            <!-- Title & Description -->
            <div class="cc-copy">
                <h3 class="cc-title">{{ __('Kami Menggunakan Cookie') }}</h3>
                <p id="cookie-consent-description" class="cc-desc">
                    {{ __('Kami menggunakan cookie untuk meningkatkan pengalaman Anda, menganalisis traffic, dan mempersonalisasi konten. Dengan menekan "Terima Semua", Anda menyetujui penggunaan semua cookie.') }}
                </p>
            </div>

            <!-- Buttons -->
            <div class="cc-actions">
                <button type="button" class="cc-btn" @click="essentialOnly()">
                    {{ __('Hanya Wajib') }}
                </button>

                <button type="button" class="cc-btn" @click="rejectNonEssential()">
                    {{ __('Tolak') }}
                </button>

                <button type="button" class="cc-btn cc-btn--primary" @click="acceptAll()">
                    {{ __('Terima Semua') }}
                </button>
            </div>
        </div>

        <!-- Detail link -->
        <div class="cc-links">
            {{ __('Kelola preferensi kapan saja dari') }}
            <a href="{{ url('/cookie-preferences') }}">{{ __('Pengaturan Cookie') }}</a>
            {{ __('atau baca') }}
            <a href="{{ url('/privacy-policy') }}">{{ __('Kebijakan Privasi') }}</a>
        </div>
    </div>
</div>
@endif
