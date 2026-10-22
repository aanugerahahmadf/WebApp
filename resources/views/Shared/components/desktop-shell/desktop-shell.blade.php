@php
    use App\Support\DesktopApp\DesktopApp;
    use Filament\Facades\Filament;

    $shellName = DesktopApp::appName();
    $panelId = DesktopApp::currentPanelId();
    $companionUrl = DesktopApp::companionUrl();

    /*
     * URL halaman untuk hotkey F1/F2/F7/F8.
     *
     * Dicari dari panel yang SEDANG dirender, jadi partial ini tetap bisa
     * dipakai ketiga panel tanpa daftar URL per panel. Kuncinya adalah NAMA
     * CLASS, bukan route: `HelpCenterPage` ada di panel Welcome, `HelpResource`
     * di panel Admin, dan seterusnya.
     *
     * `getUrl()` dibungkus try/catch karena halaman yang tidak terdaftar di
     * panel aktif akan melempar -- dan satu hotkey yang gagal ambil URL
     * sebaiknya diam, bukan membuat seluruh peta kosong.
     */
    $pageUrls = [];

    $panel = Filament::getCurrentPanel();

    foreach ($panel?->getPages() ?? [] as $page) {
        try {
            $pageUrls[class_basename($page)] = $page::getUrl();
        } catch (\Throwable) {
            // Halaman ini tidak bisa dihitung URL-nya di konteks sekarang.
        }
    }

    foreach ($panel?->getResources() ?? [] as $resource) {
        try {
            $pageUrls[class_basename($resource)] = $resource::getUrl('index');
        } catch (\Throwable) {
            // Sama seperti di atas.
        }
    }
@endphp
{{--
    Partial ini HANYA dirender di dalam shell desktop (Electron).

    Yang dikerjakannya:

      1. Menandai <html> sebagai shell desktop, supaya CSS bisa targeting
         tanpa harus menebak dari User-Agent.
      2. Memb publishes window.DesktopApp -- API kecil yang bisa dipakai
         view/JS mana pun untuk tahu "ini aplikasi desktop, buka saja
         panel saudara, jangan lempar ke browser".
      3. Mendahulukan judul aplikasi pada <title>. Electron memakai
         <title> sebagai judul jendela, jadi tanpa ini dua jendela ini
         sama-sama bernama "Dekorasi Bunga Pernikahan" dan tidak bisa
         dibedakan di taskbar.
      4. Mendaftarkan hotkey F1..F12 -- lihat `window.__dekorasiHotkey`.

    Registrasi: hook BODY_END di ketiga panel provider.
--}}
<style>
    /*
     * Shell desktop tidak punya bilah scroll browser, jadi area yang
     * boleh "menggelembung" sedikit lebih lega daripada di web.
     * Hanya berlaku di dalam jendela, website tidak tersentuh.
     */
    html.desktop-shell .fi-topbar {
        -webkit-user-select: none;
        user-select: none;
    }

    html.desktop-shell .fi-topbar input,
    html.desktop-shell .fi-topbar textarea {
        -webkit-user-select: text;
        user-select: text;
    }
</style>
<script>
    (function () {
        try {
            var shellName = @json($shellName);
            var panelId = @json($panelId);
            var companionUrl = @json($companionUrl);
            var pageUrls = @json($pageUrls);

            var root = document.documentElement;

            root.setAttribute('data-shell', 'desktop');
            root.setAttribute('data-shell-app', shellName);
            root.classList.add('desktop-shell');

            // ── API untuk view & Livewire component ───────────────────────
            // Satu-satunya sumber "ini aplikasi desktop" di sisi klien.
            // Bandingkan dengan window.AppPlatform (dari
            // platform-runtime-script) yang menjawab "platform apa";
            // ini menjawab "shell aplikasi apa".
            window.DesktopApp = {
                isDesktopApp: true,
                shellName: shellName,
                panel: panelId,
                companionUrl: companionUrl,

                /**
                 * Buka panel saudara di DALAM jendela.
                 *
                 * Navigasi biasa (window.location) dipakai, bukan
                 * window.open -- window.open di Electron membuka jendela
                 * kedua, dan yang pengguna mau dari "Buka Toko" adalah
                 * panel yang sama di jendela yang sama.
                 *
                 * Kalau panel saudara tidak ada (null), tidak terjadi apa-apa.
                 */
                openCompanion: function () {
                    if (!companionUrl) {
                        return false;
                    }

                    window.location.assign(companionUrl);

                    return true;
                },
            };

            // ── Hotkey F1..F12 ────────────────────────────────────────────
            //
            // Panel inilah yang tahu URL halaman dan struktur DOM-nya, jadi
            // hotkey yang butuh keduanya dikendalikan di sini. Main process
            // hanya meneruskan tombolnya (lewat `before-input-event`), dan
            // F11 (layar penuh) + F12 (DevTools) ditangani di sana karena
            // keduanya butuh main process.
            //
            // Peta tombolnya mengikuti urutan tombol fungsi di aplikasi
            // desktop pada umumnya:
            //
            //   F1  Bantuan          F7  Katalog
            //   F2  Edit Profil      F8  Pesanan
            //   F3  Cari             F9  Menu akun
            //   F4  Buka Panel Admin F10 Fokus ke topbar
            //   F5  Muat ulang       F11 Layar penuh  (main process)
            //   F6  Fokus ke elemen berikutnya
            //                         F12 DevTools     (main process)
            // Elemen yang benar-benar terlihat.
            //
            // `offsetParent` TIDAK bisa dipakai: dropdown Filament memakai
            // `position: fixed`, dan untuk elemen seperti itu `offsetParent`
            // selalu null -- jadi elemen yang terlihatpun terbaca tak
            // terlihat. `getClientRects()` yang benar.
            function isVisible(element) {
                return !!element && element.getClientRects().length > 0;
            }

            function gotoPage(className) {
                var url = pageUrls[className];

                if (!url) {
                    return false;
                }

                window.location.assign(url);

                return true;
            }

            function openGlobalSearch() {
                var search = document.querySelector('.fi-global-search');
                var input = search ? search.querySelector('.fi-global-search-field input') : null;

                // Kalau kolomnya sudah terbuka, jangan toggle -- cukup
                // fokuskan. Memakai trigger di keadaan itu akan MENUTUP
                // pencarian yang sudah terbuka.
                if (input && isVisible(input)) {
                    input.focus();

                    return true;
                }

                var trigger = search ? search.querySelector('button') : null;

                if (trigger) {
                    trigger.click();

                    // Kolom pencarian dirender satu tick setelah tombolnya
                    // ditekan, jadi inputnya baru bisa difokuskan
                    // sesudahnya.
                    setTimeout(function () {
                        var field = document.querySelector('.fi-global-search-field input');

                        if (field) {
                            field.focus();
                        }
                    }, 200);

                    return true;
                }

                // Cadangan: Filament membuka modal pencarian lewat event ini.
                if (search) {
                    search.dispatchEvent(new CustomEvent('focus-first-global-search', { bubbles: true }));

                    return true;
                }

                return false;
            }

            function focusNextElement() {
                var focusable = Array.prototype.slice.call(
                    document.querySelectorAll(
                        'a[href], button:not([disabled]), input:not([disabled]), ' +
                        'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                    ),
                ).filter(isVisible);

                if (!focusable.length) {
                    return false;
                }

                var index = focusable.indexOf(document.activeElement);
                var next = focusable[(index + 1) % focusable.length];

                next.focus();

                return true;
            }

            function openAccountMenu() {
                // Tamu: tombol akun di `topbar-auth-actions`.
                // Sudah sign in: user menu bawaan Filament.
                var trigger = document.querySelector(
                    '.fi-user-menu button, .fi-welcome-auth-actions-account',
                );

                if (!trigger) {
                    return false;
                }

                trigger.click();

                return true;
            }

            function focusTopbar() {
                var topbar = document.querySelector('.fi-topbar');

                if (!topbar) {
                    return false;
                }

                // Hanya elemen yang terlihat: elemen pertama di topbar
                // biasanya tombol tutup sidebar yang tersembunyi di layar
                // lebar, dan `.focus()` ke elemen tersembunyi tidak
                // memindahkan fokus.
                var target = Array.prototype.slice.call(
                    topbar.querySelectorAll('input, button, a, [tabindex]'),
                ).filter(isVisible)[0];

                if (!target) {
                    return false;
                }

                target.focus();

                return true;
            }

            window.__dekorasiPageUrls = pageUrls;

            window.__dekorasiHotkey = function (key) {
                switch (key) {
                    case 'F1':
                        // Bantuan: panel Welcome punya HelpCenterPage, panel
                        // Admin punya HelpResource.
                        return gotoPage('HelpCenterPage') || gotoPage('HelpResource');
                    case 'F2':
                        return gotoPage('EditProfilePage');
                    case 'F3':
                        return openGlobalSearch();
                    case 'F4':
                        return window.DesktopApp.openCompanion();
                    case 'F5':
                        window.location.reload();

                        return true;
                    case 'F6':
                        return focusNextElement();
                    case 'F7':
                        return gotoPage('CatalogResource');
                    case 'F8':
                        return gotoPage('OrderResource');
                    case 'F9':
                        return openAccountMenu();
                    case 'F10':
                        return focusTopbar();
                    default:
                        return false;
                }
            };

            // ── Judul jendela ─────────────────────────────────────────────
            // Electron memakai <title> sebagai judul jendela. Filament
            // menulis ulang judul itu tiap kali Livewire berpindah
            // halaman, jadi awalan perlu dipasang ulang tiap kali berubah.
            var titleElement = document.querySelector('title');
            var separator = ' — ';

            function applyTitle() {
                if (!titleElement) {
                    return;
                }

                var current = (titleElement.textContent || '').trim();

                if (!current || current.indexOf(shellName + separator) === 0) {
                    return;
                }

                titleElement.textContent = shellName + separator + current;
            }

            applyTitle();

            if (titleElement && typeof MutationObserver === 'function') {
                new MutationObserver(applyTitle).observe(titleElement, {
                    childList: true,
                    characterData: true,
                    subtree: true,
                });
            }
        } catch (e) {
            console.error('[DesktopApp] Runtime script error:', e);
        }
    })();
</script>
