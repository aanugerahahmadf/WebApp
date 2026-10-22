{{--
    Menyesuaikan tinggi #messages-container dengan tinggi yang benar-benar
    tersisa di layar.

    Kenapa partial, bukan script inline di tiap blade halaman: ketiga panel
    (User, Admin, Welcome) punya logika yang PERSIS sama dan sudah pernah
    ditulis tiga kali. Perbedaannya cuma satu angka keras -- 64px untuk
    bottom-nav di panel User/Welcome yang tidak ada di panel Admin -- dan angka
    itu sekarang dibaca dari DOM, bukan ditebak.

    Cara kerjanya: `rect.top` sudah menyertakan tinggi topbar, baris breadcrumb,
    dan padding whatever yang ada di atas. Jadi yang perlu dihitung hanya sisa
    dari bawah. `visualViewport` dipakai, bukan `innerHeight`, karena hanya itu
    yang ikut menyusut saat keyboard HP terbuka -- tanpa itu,area ketik akan
    menutupi separuh chat.

    Pagar bawah 280px itu bukan asal: `Math.max`
    280px sadar bahwa di ponsel pendek dengan keyboard terbuka, sisa tinggi
    bisa negatif, dan gelembung aksi pesan yang di-teleport ke <body> tetap
    butuh tempat.
--}}
<script data-navigate-once>
    (function () {
        var MIN_HEIGHT = 280;
        var GAP = 8;

        // Bottom-nav HP (Shared/components/mobile-bottom-nav). Panel Admin
        // tidak punya, jadi jaraknya diukur dari DOM, bukan angka tetap.
        // akan membuat chat pendek di panel yang tidak punya nav.
        function bottomNavClearance() {
            var nav = document.querySelector('.fi-bottom-nav');

            if (!nav) {
                return 0;
            }

            // Elementnya ada tapi disembunyikan CSS (desktop, atau auth page) --
            // offsetParent null adalah ciri paling murah untuk "ada di DOM tapi disembunyikan".
            if (!nav.offsetParent && getComputedStyle(nav).position !== 'fixed') {
                return 0;
            }

            var rect = nav.getBoundingClientRect();

            if (rect.height <= 0) {
                return 0;
            }

            return rect.height + safeAreaBottom();
        }

        // env(safe-area-inset-bottom) tidak bisa dibaca langsung dari JS, jadi
        // ditanyakan ke elemen dummy yang tingginya diset dari CSS.
        function safeAreaBottom() {
            try {
                var probe = document.getElementById('_sai_probe');

                if (!probe) {
                    probe = document.createElement('div');
                    probe.id = '_sai_probe';
                    probe.style.cssText = 'position:fixed;bottom:0;height:env(safe-area-inset-bottom,0px);width:0;pointer-events:none;visibility:hidden;';
                    document.body.appendChild(probe);
                }

                return probe.getBoundingClientRect().height || 0;
            } catch (error) {
                return 0;
            }
        }

        function fitMessagesContainer() {
            var container = document.getElementById('messages-container');

            if (!container) {
                return;
            }

            var viewportHeight = window.visualViewport
                ? window.visualViewport.height
                : window.innerHeight;

            var top = container.getBoundingClientRect().top;

            var bottomClearance;

            if (window.innerWidth >= 1024) {
                // Desktop: footer Filament, kalau ada.
                var footer = document.querySelector('.fi-footer') || document.querySelector('footer');
                var footerHeight = footer ? footer.getBoundingClientRect().height : 0;

                bottomClearance = footerHeight + GAP * 2;
            } else {
                bottomClearance = bottomNavClearance() + GAP;
            }

            container.style.height = Math.max(viewportHeight - top - bottomClearance, MIN_HEIGHT) + 'px';
        }

        function scheduleFit() {
            requestAnimationFrame(function () {
                requestAnimationFrame(fitMessagesContainer);
            });
        }

        document.addEventListener('DOMContentLoaded', scheduleFit);
        window.addEventListener('resize', fitMessagesContainer);

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', fitMessagesContainer);
            window.visualViewport.addEventListener('scroll', fitMessagesContainer);
        }

        // Navigasi Livewire mengganti seluruh isi halaman; container yang baru
        // belum punya tinggi sampai dihitung ulang.
        document.addEventListener('livewire:navigated', scheduleFit);

        // Fallback untuk hydrasi lambat.
        setTimeout(fitMessagesContainer, 200);
        setTimeout(fitMessagesContainer, 600);
    })();
</script>
