{{-- Emoji Picker — portal ke body agar tidak terpotong overflow --}}
<div
    x-data="{
        state: $wire.$entangle('{{ $getComponent()->getStatePath() }}'),
        open: false,
        portalEl: null,

        init() {
            // Buat portal element di body
            this.portalEl = document.createElement('div');
            this.portalEl.style.cssText = 'position:fixed;z-index:9999;display:none;';
            document.body.appendChild(this.portalEl);

            // Pasang emoji-picker element
            const isDark = document.documentElement.classList.contains('dark');
            const picker = document.createElement('emoji-picker');
            picker.classList.add(isDark ? 'dark' : 'light');
            this.portalEl.appendChild(picker);

            picker.addEventListener('emoji-click', (e) => {
                this.state = (this.state ?? '') + e.detail.unicode;
                this.close();
            });

            // Tutup saat klik di luar
            document.addEventListener('click', (e) => {
                if (this.open && !this.portalEl.contains(e.target) && !this.$el.contains(e.target)) {
                    this.close();
                }
            });

            // Cleanup saat komponen destroy
            this.$cleanup(() => {
                if (this.portalEl) this.portalEl.remove();
            });
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            const btn = this.$refs.btn;
            const rect = btn.getBoundingClientRect();
            const pickerH = 400;
            const pickerW = 350;

            // Posisi: coba di atas tombol, kalau tidak muat taruh di bawah
            let top = rect.top - pickerH - 8;
            if (top < 8) top = rect.bottom + 8;

            // Posisi horizontal: rata kanan tombol, jangan keluar layar
            let left = rect.right - pickerW;
            if (left < 8) left = 8;
            if (left + pickerW > window.innerWidth - 8) left = window.innerWidth - pickerW - 8;

            this.portalEl.style.top  = top + 'px';
            this.portalEl.style.left = left + 'px';
            this.portalEl.style.display = 'block';
            this.open = true;
        },

        close() {
            this.portalEl.style.display = 'none';
            this.open = false;
        }
    }"
>
    <div x-ref="btn" x-on:click.stop="toggle()" class="emoji-picker-button cursor-pointer">
        @include($childView)
    </div>
</div>
