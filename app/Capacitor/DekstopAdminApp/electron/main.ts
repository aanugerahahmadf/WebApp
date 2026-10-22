import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { BrowserWindow, Menu, clipboard, screen, shell, type BrowserWindowConstructorOptions, type MenuItemConstructorOptions } from 'electron';
import { createCapacitorElectronApp } from '@capawesome/capacitor-electron';

import config from './capacitor.electron.config';

/**
 * Server + panel yang dibuka shell ini.
 *
 * Sumber tunggalnya: `server.url` di ../capacitor.config.json. File itu
 * ditulis ulang ke `generated/capacitor.config.json` setiap `cap sync`,
 * jadi di sini cukup membacanya -- tidak ada konstanta URL yang bisa
 * basi. `npm run set:url -- <url>` tetap jadi satu-satunya cara
 * menggeser target.
 */
function resolveServer(): { origin: string; panelPath: string } {
    const generatedPath = join(__dirname, '..', 'generated', 'capacitor.config.json');

    let panelUrl = process.env.CAPACITOR_ELECTRON_DEV_SERVER_URL ?? '';

    try {
        const generated = JSON.parse(readFileSync(generatedPath, 'utf8')) as {
            server?: { url?: string };
        };

        if (typeof generated.server?.url === 'string' && generated.server.url !== '') {
            panelUrl = generated.server.url;
        }
    } catch {
        // generated/ belum ada (belum pernah `cap sync`). Pakai env var
        // yang dipasang CLI, atau biarkan runtime yang melaporkannya.
    }

    const url = new URL(panelUrl);

    // `CAPACITOR_ELECTRON_PANEL_PATH` menimpa panel yang dibuka pertama,
    // tanpa mengubah `server.url` (yang dipakai Android dan iOS juga).
    const override = process.env.CAPACITOR_ELECTRON_PANEL_PATH;

    return {
        origin: url.origin,
        panelPath: (typeof override === 'string' && override !== ''
            ? override
            : url.pathname
        ).replace(/\/+$/, ''),
    };
}

const { origin, panelPath } = resolveServer();

/**
 * Runtime @capawesome/capacitor-electron memakai
 * CAPACITOR_ELECTRON_DEV_SERVER_URL sebagai DUA hal sekaligus:
 *
 *   1. URL yang dimuat di jendela utama.
 *   2. DAFTAR ORIGIN TEPERCAYA: `url.startsWith(devServerUrl)`.
 *
 * (2) yang menentukan navigasi boleh staying di dalam jendela. Kalau
 * nilainya berisi path panel (`.../admin/signin`), URL panel lain --
 * `/home` dari Sign Out, `/user` dari menu akun -- jatuh di luar prefix
 * itu, lalu guard runtime melemparnya ke browser sistem. Praktisnya
 * itu berarti satu-satunya jalan keluar dari aplikasi desktop adalah
 * menutup jendela.
 *
 * Jadi di sini dipaksa jadi ORIGIN SAJA. Path panelnya dipisah ke
 * `panelPath` dan dipakai untuk menentukan halaman yang dibuka pertama.
 */
process.env.CAPACITOR_ELECTRON_DEV_SERVER_URL = origin;

/**
 * Menu bar: DIHAPUS TOTAL.
 *
 * Menu bawaan Electron (File / Edit / View / Window) itu milik developer --
 * lengkap dengan "Toggle DevTools", "Reload", "Zoom", dan seterusnya. Untuk
 * pengguna akhir yang cuma membuka katalog, itu cuma noise. Jadi baris
 * menunya dihapus, bukan disembunyikan.
 *
 * Dan tidak ada tombol Refresh di menu bar pun, karena **Windows tidak
 * merender ikon PNG pada item menu bar Electron**. Yang diuji:
 *
 *   - `label: ''` + icon        -> item tidak dirender sama sekali
 *   - `label: ' '` + icon       -> item dirender, tapi ikonnya TIDAK tampil
 *   - `label: 'R'` + icon       -> yang tampil hanya huruf "R"
 *
 * Jadi ikon PNG hanya bisa dipakai di POPUP menu (menu konteks klik-kanan),
 * dan itulah tempat semua perintahnya dikumpulkan.
 *
 * `Menu.setApplicationMenu(null)` dipasang SETELAH app siap, kalau tidak
 * Electron menimpanya dengan menu bawaannya.
 */
function installApplicationMenu(): void {
    Menu.setApplicationMenu(null);
}

/**
 * Menu konteks klik-kanan: tempat semua perintah.
 *
 * Menu konteks bawaan Chromium hilang begitu menu aplikasi Electron
 * diganti, dan menu "Edit" bawaan Electron (yang membawa role
 * copy/paste/selectAll) juga dibuang. Jadi menu konteksnya dibangun
 * sendiri di sini.
 *
 * Isinya menyesuaikan konteks: kalau yang diklik bukan field yang bisa
 * diedit, tidak ada "Cut"/"Paste" -- yang ada cuma "Copy" dan "Copy Link
 * Address". Sisanya (zoom, fullscreen, DevTools) selalu ada.
 */
function installContextMenu(window: BrowserWindow): void {
    window.webContents.on('context-menu', (_event, params) => {
        const template: MenuItemConstructorOptions[] = [
            {
                // Refresh: TEKS SAJA. Ikonnya (lingkaran panah emas di
                // `assets/refresh.png`) sengaja tidak dipakai di sini --
                // yang dituju cuma teksnya.
                label: 'Refresh',
                click: () => {
                    window.reload();
                },
            },
            { type: 'separator' },
        ];

        if (params.isEditable) {
            template.push(
                { role: 'undo' },
                { role: 'redo' },
                { type: 'separator' },
                { role: 'cut' },
                { role: 'copy' },
                { role: 'paste' },
                { role: 'pasteAndMatchStyle' },
                { role: 'delete' },
                { role: 'selectAll' },
            );
        } else {
            template.push({ role: 'copy' });

            // "Copy Link Address" tidak punya role bawaan Electron.
            if (params.linkURL) {
                template.push({
                    label: 'Copy Link Address',
                    click: () => {
                        clipboard.writeText(params.linkURL);
                    },
                });
            }
        }

        template.push(
            { type: 'separator' },
            { role: 'zoomIn' },
            { role: 'zoomOut' },
            { role: 'resetZoom' },
            { type: 'separator' },
            {
                label: 'Toggle Full Screen',
                click: () => {
                    window.setFullScreen(!window.isFullScreen());
                },
            },
            {
                label: 'Toggle Developer Tools',
                click: () => {
                    window.webContents.toggleDevTools();
                },
            },
            {
                // "Inspect Element" bukan role Electron, tapi perintah
                // `inspectElement()` yang membuka DevTools persis di elemen
                // yang diklik.
                label: 'Inspect Element',
                click: () => {
                    window.webContents.inspectElement(params.x, params.y);
                },
            },
        );

        Menu.buildFromTemplate(template).popup({ window });
    });
}

/**
 * Semua tombol fungsi (F1..F12) yang bisa dipakai di shell desktop.
 *
 * Daftar ini jadi satu-satunya tempat yang tahu tombol mana yang
 * ditangani, jadi menambah hotkey baru cukup di satu file.
 */
const FUNCTION_KEYS = [
    'F1', 'F2', 'F3', 'F4', 'F5', 'F6',
    'F7', 'F8', 'F9', 'F10', 'F11', 'F12',
];

/**
 * Shortcut yang biasanya dibawa menu Electron, sekarang ditangkap manual.
 *
 * Menu bar sudah dihapus, jadi tidak ada lagi tempat accelerator dicatat.
 * Tanpa ini:
 *
 *   - F1..F12 mati
 *   - Ctrl+C / Ctrl+V / Ctrl+X tidak bisa menyalin-tempel
 *   - Ctrl+R tidak me-reload
 *
 * `before-input-event` menangkap penekanan tombol SEBELUM halaman
 * memprosesnya, jadi di sini perintahnya bisa dijalankan sendiri.
 *
 * PEMBAGIAN TANGGUNG JAWAB:
 *
 *   - F11 (layar penuh) dan F12 (DevTools) MEMBUTUHKAN main
 *     process, jadi ditangani di sini.
 *   - Sisanya (F1..F10) butuh tahu URL halaman panel dan struktur DOM-nya
 *     -- dua hal yang hanya diketahui renderer. Jadi diteruskan ke
 *     `window.__dekorasiHotkey()` yang dipasang partial desktop-shell.
 */
function installKeyboardShortcuts(window: BrowserWindow): void {
    window.webContents.on('before-input-event', (event, input) => {
        if (input.type !== 'keyDown') {
            return;
        }

        if (FUNCTION_KEYS.includes(input.key)) {
            event.preventDefault();

            if (input.key === 'F12') {
                window.webContents.toggleDevTools();

                return;
            }

            // F11 = fullscreen. Role `togglefullscreen` di menu konteks cuma
            // memberi label; akseleratornya tetap harus dicatat di sini.
            if (input.key === 'F11') {
                window.setFullScreen(!window.isFullScreen());

                return;
            }

            void window.webContents
                .executeJavaScript(
                    `window.__dekorasiHotkey ? window.__dekorasiHotkey(${JSON.stringify(input.key)}) : false`,
                )
                .catch(() => {
                    // Halaman sedang berpindah (Livewire navigate) atau
                    // context-nya sudah dibuang -- hotkey dilewati saja.
                });

            return;
        }

        if (input.control && input.shift && input.key.toLowerCase() === 'i') {
            event.preventDefault();
            window.webContents.toggleDevTools();

            return;
        }

        if (!input.control || input.shift || input.alt) {
            return;
        }

        switch (input.key.toLowerCase()) {
            case 'r':
                event.preventDefault();
                window.reload();
                break;
            case 'c':
                event.preventDefault();
                window.webContents.copy();
                break;
            case 'x':
                event.preventDefault();
                window.webContents.cut();
                break;
            case 'v':
                event.preventDefault();
                window.webContents.paste();
                break;
            case 'a':
                event.preventDefault();
                window.webContents.selectAll();
                break;
        }
    });
}

/**
 * Jendela utama: state yang tersimpan DIVALIDASI dulu.
 *
 * Runtime `@capawesome/capacitor-electron` menyimpan ukuran & posisi
 * jendela ke `userData/capacitor-electron-window-state.json`, lalu memakai
 * state itu TANPA validasi. Dua hal bisa membuat jendela terbuka dengan
 * cara yang tidak bisa dipakai:
 *
 *   1. State dari sesi yang minimize menyimpan koordinat **-25600**
 *      (posisi ikon di taskbar) -> jendela terbuka DI LUAR LAYAR. Tidak
 *      kelihatan, dan TIDAK ada error apa pun. Ini yang terjadi: file
 *      state app toko berisi `x: -25600, y: -25600`.
 *   2. State dari jendela yang dikecilkan menyimpan ukuran di bawah
 *      minWidth/minHeight -> jendela jadi sebesar title bar saja.
 *
 * Jadi: ukuran yang lebih kecil dari minimum diganti dengan ukuran
 * config, dan posisi yang tidak masuk area kerja layar DIBUANG -- Electron
 * lalu menengahkan jendela sendiri.
 */
function createMainWindow(options: BrowserWindowConstructorOptions): BrowserWindow {
    const minWidth = options.minWidth ?? 0;
    const minHeight = options.minHeight ?? 0;

    if (minWidth > 0 && (options.width ?? 0) < minWidth) {
        options.width = minWidth;
    }

    if (minHeight > 0 && (options.height ?? 0) < minHeight) {
        options.height = minHeight;
    }

    if (typeof options.x === 'number' && typeof options.y === 'number') {
        const area = screen.getDisplayNearestPoint({ x: options.x, y: options.y }).workArea;
        const width = options.width ?? 0;
        const height = options.height ?? 0;

        const insideWorkArea =
            options.x + width > area.x &&
            options.x < area.x + area.width &&
            options.y + height > area.y &&
            options.y < area.y + area.height;

        if (!insideWorkArea) {
            delete options.x;
            delete options.y;
        }
    }

    return new BrowserWindow(options);
}

const capacitorApp = createCapacitorElectronApp({
    ...config,

    hooks: {
        ...config.hooks,

        windowFactory: createMainWindow,

        onWindowCreated: async (window) => {
            await config.hooks?.onWindowCreated?.(window);

            /**
             * Ikon jendela memakai logo merek (assets/icon.png).
             *
             * Kalau aplikasinya sudah dipaket, ikon diambil dari
             * `assets/icon.ico` yang di-embed electron-builder ke dalam exe --
             * itu sudah otomatis. Tapi saat dijalankan dari sumber
             * (`npm run desktop`), tidak ada exe sama sekali, jadi Electron
             * memakai ikon default-nya dan taskbar menampilkan logo Electron.
             *
             * `setIcon()` menutup celah itu. Path-nya relatif terhadap
             * `electron/build/` (hasil kompilasi main.ts), dan folder `assets/`
             * ikut dipaket electron-builder, jadi path yang sama berlaku untuk
             * mode dev maupun mode paket.
             *
             * macOS tidak punya ikon taskbar per jendela (dock icon datang
             * dari bundle .app), jadi pemanggilannya dilewati di sana.
             */
            if (process.platform !== 'darwin') {
                window.setIcon(join(__dirname, '..', 'assets', 'icon.png'));
            }

            /**
             * Klik kanan = menu konteks (tempat semua perintah developer).
             *
             * Menu konteks bawaan Chromium hilang begitu menu aplikasi
             * Electron diganti, dan menu "Edit" bawaan Electron (yang
             * membawa role copy/paste/selectAll) juga dibuang. Jadi
             * isinya dibangun sendiri.
             */
            installContextMenu(window);

            /**
             * F12 dan Ctrl+C/V butuh ditangkap manual -- menu bar sudah
             * tidak punya tempat untuk mendaftarkan accelerator-nya.
             */
            installKeyboardShortcuts(window);

            /**
             * Pop-up Google Identity Services WAJIB boleh dibuka di DALAM
             * aplikasi.
             *
             * Guard bawaan runtime menolak SEMUA window.open dan melemparnya
             * ke browser sistem. Kalau popup Google dibuka di browser sistem,
             * tokennya tidak pernah bisa kembali ke jendela ini -- postMessage
             * lintas aplikasi tidak mungkin -- jadi "Continue With Google"
             * tidak akan pernah selesai.
             *
             * Sisanya tetap dibuka di browser sistem, sama seperti di
             * website: aplikasi desktop bukan tempat menjelajah web.
             */
            window.webContents.setWindowOpenHandler(({ url }) => {
                if (url.startsWith('https://accounts.google.com/')) {
                    return {
                        action: 'allow',
                        overrideBrowserWindowOptions: {
                            width: 480,
                            height: 640,
                            autoHideMenuBar: true,
                            webPreferences: {
                                contextIsolation: true,
                                nodeIntegration: false,
                                sandbox: true,
                            },
                        },
                    };
                }

                if (url.startsWith('https://') || url.startsWith('http://') || url.startsWith('mailto:')) {
                    void shell.openExternal(url);
                }

                return { action: 'deny' };
            });

            /**
             * Halaman pertama harus panel yang benar.
             *
             * Jendela dimuat di ORIGIN, jadi server yang memutuskan halaman
             * mana yang terbuka (panel Welcome di root). Untuk shell admin
             * itu bukan panel yang benar -- jadi jendela diarahkan ke
             * `panelPath` (= /admin/signin).
             *
             * Kenapa tidak langsung di hook `did-finish-load`?
             *
             * Karena saat hook itu menyala, redirect 302 dari origin ke
             * panel Welcome masih bisa jalan. Kalau kita navigasi saat itu,
             * redirect tersebut yang membatalkan perpindahan kita --
             * hasilnya `ERR_ABORTED` dan jendela tetap di panel yang salah.
             *
             * Jadi perpindahan baru dijalankan setelah URL-nya BENAR-BENAR
             * stabil: tidak ada navigasi yang sedang berjalan
             * (`isLoading()`) dan URL-nya tidak berubah sejak pemeriksaan
             * sebelumnya. Kalau belum stabil, pemeriksaan diulang.
             *
             * Sifatnya SEKALI, bukan setiap navigasi. Kalau tidak,
             * perpindahan panel yang memang disengaja (Sign Out ke Welcome
             * Home, menu akun ke /user) akan ditendak balik ke panel
             * awal dan pengguna tidak bisa keluar dari panel itu.
             */
            let landingApplied = false;
            let lastUrl = '';
            let landingTimer: NodeJS.Timeout | undefined;

            const armLanding = (delay = 400): void => {
                if (landingTimer) {
                    clearTimeout(landingTimer);
                }

                landingTimer = setTimeout(attemptLanding, delay);
            };

            const attemptLanding = (): void => {
                if (landingApplied) {
                    return;
                }

                const current = window.webContents.getURL();

                if (window.webContents.isLoading() || current !== lastUrl) {
                    // Masih ada yang bergerak -- tunggu lagi.
                    lastUrl = current;

                    armLanding();

                    return;
                }

                landingApplied = true;

                if (!current.startsWith(`${origin}${panelPath}`)) {
                    void window.loadURL(`${origin}${panelPath}`).catch(() => {
                        // ERR_ABORTED: navigasi lain membatalkan yang ini.
                        // Tidak apa-apa -- runtime punya mechanism retry
                        // sendiri (installDevServerRetry).
                    });
                }
            };

            window.webContents.on('did-finish-load', () => {
                const current = window.webContents.getURL();

                if (current !== lastUrl) {
                    lastUrl = current;
                }

                armLanding();
            });
        },
    },
});

// Menu dipasang setelah app siap -- kalau lebih awal, Electron menimpanya
// dengan menu bawaannya (File / Edit / View / Window).
void capacitorApp.whenReady.then(installApplicationMenu);
