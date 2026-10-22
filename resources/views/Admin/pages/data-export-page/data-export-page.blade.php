<x-filament-panels::page>
    <div>
        {{ $this->form }}
    </div>

    {{--
        Unduhan dipicu lewat IFRAME TERSEMBUNYI, bukan redirect.

        Redirect = navigasi penuh: browser sempat memuat ulang dokumen, dan
        begitu token/sesi bergeser (mis. karena login di tab lain) livewire.js
        memunculkan dialog "This page has expired" tepat setelah unduhan
        selesai. Dengan iframe, hanya berkasnya yang turun dan halaman tetap
        seperti semula.
    --}}
    <script>
        window.addEventListener('unduh_data', (event) => {
            const url = event?.detail?.url ?? window.Livewire.find('{{ $this->getId() }}')?.downloadUrl;

            if (! url) {
                return;
            }

            const frame = document.createElement('iframe');

            frame.style.display = 'none';
            frame.src = url;

            document.body.appendChild(frame);

            window.setTimeout(() => frame.remove(), 60000);
        });
    </script>
</x-filament-panels::page>
