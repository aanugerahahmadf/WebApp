{{--
    Registrasi Alpine untuk gelembung aksi pesan ala iOS Messages.

    File ini SENGAJA dipisah dari markup gelembungnya
    (Shared/components/message-bubble/message-bubble-panel) dan hanya di-@include
    sekali dari blade HALAMAN (resources/views/{User,Admin}/pages/messages/...),
    bukan dari blade komponen Livewire.

    Alasannya: tiap baris pesan disuntik ulang oleh `wire:poll` setiap 2-3 detik.
    Kalau script-nya ikut di dalam komponen Livewire, tag <script>-nya ikut
    dirender ulang terus-menerus. Penjaga `__fmMessageBubbleRegistered` membuat
    registrasi Alpine-nya cuma jalan sekali, tapi tag script-nya sendiri tetap
    dibakar ulang tiap polling. Di level halaman, file ini baru dirender sekali
    per navigasi.

    Yang diregistrasikan hanya SATUR instance Alpine untuk satu panel chat, bukan
    satu instance per pesan. Alasannya: gelembung harus bisa keluar dari kotak
    `overflow-y-auto` yang membungkus daftar pesan. Kalau markup-nya ikut di dalam
    tiap baris pesan, gelembung untuk pesan paling atas akan terpotong tepi kotak.
    Satu panel yang di-teleport ke <body> dengan `position: fixed` bebas dari
    semua ancestor-nya, dan Livewire tidak perlu ikut-ikutan me-morph isi
    gelembung karena isinya dirender Alpine dari payload JSON.

    PENTING soal Blade di file ini -- dua jebakan yang keduanya menghasilkan
    halaman 500, bukan error yang enak dibaca:

      1. Blade mengompile direktifNYA di dalam tag <script> juga, bukan cuma di
         dalam markup. Karena itu nama direktif yang disebut di komentar
         JavaScript di bawah ditulis dengan dua tanda	at, bukan satu: Blade
         mengubahnya jadi satu `@` biasa saat render, dan yang muncul di
         komentar tetap nama direktif aslinya. Tanpa pelarian itu Blade menulis
         `make(, ...)` dan PHP gagal dengan "unexpected token ','".
      2. Aturan yang sama berlaku di dalam komentar Blade sendiri -- meski
         ditulis di antara tanda buka dan tutup komentar Blade, `@` di dalamnya
         tetap dikompile. Karena itu catatan ini sengaja tidak menyebut nama
         direktif apa pun. Kalau nanti perlu menyebutkannya, tulis dengan dua
         tanda `@` seperti poin 1.
--}}
@once
<script data-navigate-once>
    (function () {
        // Panjang tahan sebelum gelembung muncul. 420ms mengikuti angka yang
        // dipakai iOS/Android: di bawah ~300ms mulai terasa tidak sengaja, di
        // atas ~600ms pengguna sudah berpikir "kenapa tidak terjadi".
        const HOLD_MS = 420;

        // Jari yang bergerak lebih jauh dari ini berarti pengguna sedang
        // menggulir daftar pesan, bukan menahan sebuah pesan.
        const MOVE_TOLERANCE_PX = 10;

        // Jarak gelembung dari pesan, dan jarak minimum dari tepi viewport.
        const BUBBLE_GAP_PX = 10;
        const VIEWPORT_EDGE_PX = 8;

        function buildMessageBubble(Alpine) {
            Alpine.data('messageBubble', () => ({
                // State yang dirender ke DOM.
                open: false,
                payload: null,
                style: '',
                caret: '50%',
                panelBelow: false,

                // State internal, tidak perlu memicu render ulang.
                holdTimer: null,
                holdOrigin: null,
                anchorEl: null,

                // Waktu gelembung terakhir dibuka. Dipakai untuk menelan klik
                // yang masih menyisakan dari gerakan tahan: lepasnya jari setelah
                // tahan akan mendarat sebagai klik biasa pada pesan -- membuka
                // link attachment atau memicu wire:click yang tidak diminta.
                //
                // Dibatasi 350ms, bukan boolean yang harus dimatikan sendiri:
                // di perangkat sentuh browser sering tidak mengirim click sama
                // sekali setelah tahan panjang, dan boolean yang menggantung akan
                // menelan klik emoji PERTAMA pengguna -- justru aksi yang paling
                // dibutuhkan.
                openedAt: 0,

                handlers: null,

                init() {
                    this.handlers = {
                        pointerDown: (event) => this.startHold(event),
                        pointerMove: (event) => this.trackHold(event),
                        pointerUp: () => this.cancelHold(),
                        contextMenu: (event) => this.onContextMenu(event),
                        escape: (event) => {
                            if (event.key === 'Escape') {
                                this.close();
                            }
                        },
                        outside: (event) => this.onOutsidePointerDown(event),
                        click: (event) => {
                            // Klik di dalam gelembung selalu diteruskan -- itu
                            // tombol emoji dan daftar aksinya.
                            if (this.$refs.panel && this.$refs.panel.contains(event.target)) {
                                return;
                            }

                            // Elemen yang punya aksinya sendiri (pil reaksi,
                            // link attachment) tidak ditelan: pengguna mungkin
                            // sengaja menekan pillnya selagi gelembung terbuka.
                            if (event.target.closest && event.target.closest('a, button, input, textarea, select')) {
                                return;
                            }

                            if (Date.now() - this.openedAt > 350) {
                                return;
                            }

                            event.preventDefault();
                            event.stopPropagation();
                        },
                        scroll: () => this.close(),
                        resize: () => this.reposition(),
                        // Polling Livewire mengganti DOM tiap 2-3 detik. Kalau
                        // gelembung dibiarkan terbuka, koordinat yang dihitung
                        // sekali jadi jauh dari pesannya. Menghitung ulang
                        // posisinya lebih enak daripada menutup gelembung di
                        // tengah pengguna membacanya.
                        livewireUpdated: () => this.reposition(),
                    };

                    const root = this.$root;

                    root.addEventListener('pointerdown', this.handlers.pointerDown);
                    window.addEventListener('pointermove', this.handlers.pointerMove, { passive: true });
                    window.addEventListener('pointerup', this.handlers.pointerUp, true);
                    window.addEventListener('pointercancel', this.handlers.pointerUp, true);
                    root.addEventListener('contextmenu', this.handlers.contextMenu);
                    document.addEventListener('keydown', this.handlers.escape);
                    document.addEventListener('pointerdown', this.handlers.outside, true);
                    document.addEventListener('click', this.handlers.click, true);
                    window.addEventListener('resize', this.handlers.resize);
                    window.addEventListener('livewire:updated', this.handlers.livewireUpdated);

                    // Gelembung selalu mengikuti posisi gulir; begitu daftar
                    // pesan bergerak, jangkar yang dihitungnya sudah tidak valid.
                    root.addEventListener('scroll', this.handlers.scroll, { capture: true, passive: true });
                },

                destroy() {
                    this.cancelHold();

                    if (!this.handlers) {
                        return;
                    }

                    const root = this.$root;

                    root.removeEventListener('pointerdown', this.handlers.pointerDown);
                    window.removeEventListener('pointermove', this.handlers.pointerMove);
                    window.removeEventListener('pointerup', this.handlers.pointerUp, true);
                    window.removeEventListener('pointercancel', this.handlers.pointerUp, true);
                    root.removeEventListener('contextmenu', this.handlers.contextMenu);
                    document.removeEventListener('keydown', this.handlers.escape);
                    document.removeEventListener('pointerdown', this.handlers.outside, true);
                    document.removeEventListener('click', this.handlers.click, true);
                    window.removeEventListener('resize', this.handlers.resize);
                    window.removeEventListener('livewire:updated', this.handlers.livewireUpdated);
                    root.removeEventListener('scroll', this.handlers.scroll, { capture: true });
                },

                // Menahan pesan (tahan lama di HP, klik kiri tahan di desktop).
                startHold(event) {
                    // Tombol tengah dan klik kanan bukan tahan. Klik kanan
                    // ditangani onContextMenu, yang jauh lebih responsif.
                    if (typeof event.button === 'number' && event.button !== 0) {
                        return;
                    }

                    const row = this.resolveRow(event);

                    if (!row) {
                        return;
                    }

                    this.cancelHold();
                    this.anchorEl = row;
                    this.holdOrigin = { x: event.clientX, y: event.clientY };

                    this.holdTimer = window.setTimeout(() => {
                        this.holdTimer = null;
                        this.showFor(row);
                    }, HOLD_MS);
                },

                trackHold(event) {
                    if (!this.holdTimer || !this.holdOrigin) {
                        return;
                    }

                    const dx = Math.abs(event.clientX - this.holdOrigin.x);
                    const dy = Math.abs(event.clientY - this.holdOrigin.y);

                    if (dx > MOVE_TOLERANCE_PX || dy > MOVE_TOLERANCE_PX) {
                        this.cancelHold();
                    }
                },

                cancelHold() {
                    if (this.holdTimer) {
                        window.clearTimeout(this.holdTimer);
                        this.holdTimer = null;
                    }

                    this.holdOrigin = null;
                },

                // Klik kanan di desktop: versi cepat dari tahan.
                onContextMenu(event) {
                    const row = this.resolveRow(event);

                    if (!row) {
                        return;
                    }

                    // Menu bawaan browser akan muncul bersamaan dengan
                    // gelembung kalau dibiarkan -- dua popup untuk aksi yang sama.
                    event.preventDefault();
                    this.cancelHold();
                    this.showFor(row);
                },

                // Baris pesan yang sedang ditunjuk kursor atau jari. Sengaja
                // menolak elemen yang punya aksi sendiri (link attachment,
                // input) supaya menahan di sana tidak membuka gelembung yang
                // tidak pernah dipinta.
                resolveRow(event) {
                    const target = event.target;

                    if (!target || typeof target.closest !== 'function') {
                        return null;
                    }

                    const row = target.closest('[data-msg-bubble]');

                    if (!row || !this.$root.contains(row)) {
                        return null;
                    }

                    if (target.closest('a, input, textarea, select, [data-no-message-bubble]')) {
                        return null;
                    }

                    return row;
                },

                showFor(row) {
                    this.anchorEl = row;

                    try {
                        this.payload = JSON.parse(row.dataset.msgPayload || '{}');
                    } catch (error) {
                        this.payload = {};
                    }

                    this.open = true;
                    this.openedAt = Date.now();

                    // Getar pendek sebagai konfirmasi "tertangkap", hanya di
                    // perangkat yang punya motor getar.
                    if (typeof navigator.vibrate === 'function') {
                        navigator.vibrate(12);
                    }

                    this.$nextTick(() => this.reposition());
                },

                close() {
                    this.open = false;
                    this.payload = null;
                    this.anchorEl = null;
                    this.style = '';
                },

                onOutsidePointerDown(event) {
                    if (!this.open || !this.$refs.panel) {
                        return;
                    }

                    if (this.$refs.panel.contains(event.target)) {
                        return;
                    }

                    // Menahan pesan lain = pindah gelembung, ditangani oleh
                    // startHold; jangan tutup dulu lalu buka lagi.
                    if (event.target.closest && event.target.closest('[data-msg-bubble]')) {
                        return;
                    }

                    this.close();
                },

                reposition() {
                    if (!this.open || !this.anchorEl || !this.$refs.panel) {
                        return;
                    }

                    const anchorRect = this.anchorEl.getBoundingClientRect();

                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.visualViewport
                        ? window.visualViewport.height
                        : window.innerHeight;

                    // offsetWidth/offsetHeight, bukan getBoundingClientRect:
                    // panelnya sedang dalam transisi scale-95 -> scale-100,
                    // dan rect akan melaporkan ukuran yang sudah dikecilkan 5%.
                    // Itu membuat posisi gelembung meleset tipis setiap kali
                    // dibuka.
                    const panelWidth = this.$refs.panel.offsetWidth;
                    const panelHeight = this.$refs.panel.offsetHeight;

                    // Bawaan: DI ATAS pesan, persis seperti iOS Messages. Kalau
                    // tidak muat, dibalik ke bawah. Kalau dua-duanya tidak
                    // muat (pesan di tengah layar sempit), gelimbunya dijepit ke
                    // dalam viewport -- lebih baik menumpuk sedikit daripada
                    // keluar layar.
                    let below = false;
                    let top = anchorRect.top - panelHeight - BUBBLE_GAP_PX;

                    if (top < VIEWPORT_EDGE_PX) {
                        below = true;
                        top = anchorRect.bottom + BUBBLE_GAP_PX;
                    }

                    top = Math.min(
                        Math.max(top, VIEWPORT_EDGE_PX),
                        Math.max(VIEWPORT_EDGE_PX, viewportHeight - panelHeight - VIEWPORT_EDGE_PX),
                    );

                    // Tengah-kan gelembung terhadap pesan, lalu jepit ke viewport.
                    let left = anchorRect.left + (anchorRect.width / 2) - (panelWidth / 2);

                    left = Math.min(
                        Math.max(left, VIEWPORT_EDGE_PX),
                        Math.max(VIEWPORT_EDGE_PX, viewportWidth - panelWidth - VIEWPORT_EDGE_PX),
                    );

                    // Panelnya ada di dalam <body> (x-teleport), jadi tidak ada
                    // ancestor dengan transform/filter yang bisa membuat
                    // `fixed` dihitung relatif terhadap sesuatu selain viewport.
                    // Koreksi di bawah hanya jaring pengaman kalau ternyata ada
                    // ancestor seperti itu: ambangnya jauh lebih besar dari
                    // efek transisi scale, supaya efek scale-95 tidak ikut
                    // diperbaiki menjadi geser yang salah.
                    const measured = this.$refs.panel.getBoundingClientRect();
                    const skewX = measured.left - left;
                    const skewY = measured.top - top;

                    if (Math.abs(skewX) > 24 || Math.abs(skewY) > 24) {
                        left -= skewX;
                        top -= skewY;
                    }

                    this.panelBelow = below;
                    this.style = 'left: ' + Math.round(left) + 'px; top: ' + Math.round(top) + 'px;';

                    // Ujung panah mengikuti titik tengah pesan, dijepit supaya
                    // tidak keluar dari sudut gelembung.
                    const caretX = Math.min(
                        Math.max(anchorRect.left + (anchorRect.width / 2) - left, 20),
                        Math.max(20, panelWidth - 20),
                    );

                    this.caret = Math.round(caretX) + 'px';
                },

                emojis() {
                    return (this.payload && this.payload.emojis) || [];
                },

                actions() {
                    return (this.payload && this.payload.actions) || [];
                },

                react(emoji) {
                    const payload = this.payload;

                    this.close();

                    if (!payload) {
                        return;
                    }

                    const wire = this.wire();

                    if (wire) {
                        wire.toggleReaction(payload.id, emoji);
                    }
                },

                run(action) {
                    const payload = this.payload;

                    this.close();

                    if (!payload || !action) {
                        return;
                    }

                    // 'copy' tidak menyentuh server sama sekali; sisanya
                    // meneruskan ke method Livewire dengan argumen yang sudah
                    // disiapkan di payload.
                    if (action.type === 'copy') {
                        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            navigator.clipboard.writeText(action.copy || '');
                        }

                        return;
                    }

                    if (!action.method) {
                        return;
                    }

                    const wire = this.wire();

                    if (!wire) {
                        return;
                    }

                    wire[action.method].apply(wire, action.args || []);
                },

                // Magic $wire milik Livewire tersedia sebagai properti pada proxy
                // Alpine. Jalur kedua dipakai kalau akses magic gagal, karena
                // gelembung berada di dalam <body> setelah teleport -- posisinya
                // sudah tidak bisa ditebak dari $root.
                wire() {
                    if (this.$wire) {
                        return this.$wire;
                    }

                    const host = document.querySelector('[wire\\:id]');
                    const data = host ? Alpine.$data(host) : null;

                    return (data && data.$wire) || null;
                },
            }));
        }

        if (window.__fmMessageBubbleRegistered) {
            return;
        }

        window.__fmMessageBubbleRegistered = true;

        document.addEventListener('alpine:init', () => buildMessageBubble(window.Alpine));

        // Kalau Alpine ternyata sudah boot sebelum partial ini masuk -- navigasi
        // Livewire, atau halaman yang memuat partial ini lewat @@include dinamis
        // -- event `alpine:init` sudah lewat. Daftarkan langsung; Alpine masih
        // bisa menerima provider baru untuk elemen yang dirender sesudahnya.
        if (window.Alpine) {
            buildMessageBubble(window.Alpine);
        }
    })();
</script>
@endonce
