/**
 * Menjalankan shell desktop Electron untuk APLIKASI TOKO (storefront + akun).
 *
 * Kenapa tidak `npx cap run @capawesome/capacitor-electron`?
 *
 * CLI paket itu mengecek dev server dengan `fetch()` lalu TIDAK menutup body
 * responsnya. Saat `AbortSignal.timeout(3000)` menyala, undici meledak
 * dengan AssertionError yang tidak tertangani -- proses CLI mati dengan
 * kode 3221226505 SETELAH jendela Electron sebenarnya sudah terbuka.
 *
 * Jadi prosesnya dibunuh di depan mata padahal aplikasinya jalan, dan
 * `npm run desktop` selalu berakhir dengan error. Script ini melakukan
 * langkah yang sama (build -> sync -> compile -> launch) tanpa fetch yang
 * bocor itu, dan exit dengan kode yang benar saat jendela ditutup.
 *
 * Langkah-langkahnya:
 *   1. `vite build`             -> bundle web ke dist/
 *   2. `cap sync`              -> dist/ -> electron/app/ + generated/capacitor.config.json
 *   3. `tsc`                   -> electron/main.ts -> electron/build/main.js
 *   4. `electron .`            -> jendela desktop
 *
 * Pemakaian:
 *   node scripts/run-desktop.mjs
 */

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const electronDir = resolve(appRoot, 'electron');

const isWindows = process.platform === 'win32';

/**
 * Jalankan perintah sinkron. Berhenti dengan kode yang sama kalau gagal.
 */
function run(command, args, label, cwd = appRoot) {
    process.stdout.write(`[desktop] ${label}...\n`);

    const result = spawnSync(command, args, {
        cwd,
        stdio: 'inherit',
        shell: isWindows,
    });

    if (result.status !== 0) {
        process.stderr.write(`[desktop] ${label} gagal (kode ${result.status ?? 'null'}).\n`);

        process.exit(result.status ?? 1);
    }
}

/**
 * Cek apakah server hidup, TANPA fetch().
 *
 * `fetch()` + `AbortSignal.timeout()` adalah sumber bug yang membuat CLI
 * paket mati -- body responsnya menggantung dan undici meledak saat
 * timeout-nya menyala. Di sini socket-nya dimatikan seketika setelah
 * header diterima, jadi tidak ada yang menggantung.
 *
 * Pakai HEAD, bukan GET: halaman panel itu berat (ratusan KB HTML),
 * dan `php artisan serve` tanpa worker memproses satu request pada satu
 * waktu -- request GET yang dibuang masih bisa memblokir request lain
 * beberapa detik.
 */
function isReachable(url, timeoutMs = 8000) {
    return new Promise((resolvePromise) => {
        const client = url.protocol === 'https:' ? https : http;

        const request = client.request(url, { method: 'HEAD' }, (response) => {
            response.destroy();

            resolvePromise(true);
        });

        request.on('error', () => resolvePromise(false));

        request.setTimeout(timeoutMs, () => {
            request.destroy();

            resolvePromise(false);
        });

        request.end();
    });
}

/**
 * Cek berkali-kali. Server dev sering masih sibuk merender halaman
 * berat ketika jendela mau dibuka, dan satu percobaan yang gagal akan
 * membuat orang mengira servernya mati.
 */
async function waitForServer(url, attempts = 3) {
    for (let attempt = 1; attempt <= attempts; attempt++) {
        if (await isReachable(url)) {
            return true;
        }

        if (attempt < attempts) {
            process.stdout.write(`[desktop] server belum merespons, mencoba lagi (${attempt}/${attempts})...\n`);

            await new Promise((r) => setTimeout(r, 1500));
        }
    }

    return false;
}

/**
 * URL panel + origin server, dibaca dari capacitor.config.json.
 *
 * `server.url` adalah satu-satunya sumber kebenaran; `npm run set:url`
 * menulisnya. Dipisah jadi origin + path karena runtime Electron memakai
 * variabel lingkungan yang sama untuk dua hal: URL yang dimuat dan daftar
 * origin tepercaya. Lihat electron/main.ts untuk penjelasan lengkapnya.
 *
 * `--panel <path>` menimpa panel yang dibuka pertama tanpa mengubah
 * `server.url` -- berguna untuk langsung membuka halaman tertentu saat
 * sedang menelusuri.
 */
function resolveServer() {
    const config = JSON.parse(readFileSync(resolve(appRoot, 'capacitor.config.json'), 'utf8'));
    const raw = config?.server?.url;

    if (typeof raw !== 'string' || raw === '') {
        throw new Error('server.url belum diisi di capacitor.config.json. Jalankan: npm run set:url -- <url>');
    }

    const url = new URL(raw);

    const panelIndex = process.argv.indexOf('--panel');
    const override = panelIndex !== -1 ? process.argv[panelIndex + 1] : undefined;

    return {
        origin: url.origin,
        panelPath: (typeof override === 'string' && override !== '' ? override : url.pathname).replace(/\/+$/, ''),
    };
}

// ── 1. build bundle web ─────────────────────────────────────────────────────
run('npm', ['run', 'build'], 'build bundle web');

// ── 2. sync ke platform electron ────────────────────────────────────────────
run('npx', ['cap', 'sync', '@capawesome/capacitor-electron'], 'sync platform electron');

// ── 3. compile main process ─────────────────────────────────────────────────
run('npm', ['run', 'build'], 'compile electron/main.ts', electronDir);

const { origin, panelPath } = resolveServer();

process.stdout.write(`[desktop] server: ${origin}\n`);
process.stdout.write(`[desktop] panel : ${panelPath}\n`);

if (! (await waitForServer(`${origin}${panelPath}`))) {
    process.stderr.write(
        `[desktop] Server ${origin} tidak merespons.\n` +
        `[desktop] Jalankan dulu: php artisan serve --host=127.0.0.1 --port=8000\n`,
    );

    process.exit(1);
}

// ── 4. jalankan jendela ─────────────────────────────────────────────────────
const electronBin = resolve(
    electronDir,
    'node_modules',
    '.bin',
    isWindows ? 'electron.cmd' : 'electron',
);

if (! existsSync(electronBin)) {
    process.stderr.write(`[desktop] Electron belum terpasang. Jalankan: npm --prefix electron install\n`);

    process.exit(1);
}

process.stdout.write('[desktop] membuka jendela...\n');

const child = spawn(`"${electronBin}" .`, {
    cwd: electronDir,
    stdio: 'inherit',
    shell: true,
    env: {
        ...process.env,
        // Dipaksa jadi ORIGIN, bukan path panel: runtime memakai nilai ini
        // sebagai daftar origin tepercaya, jadi navigasi antar panel
        // (/home, /user, /admin) tetap di dalam jendela.
        CAPACITOR_ELECTRON_DEV_SERVER_URL: origin,
        // Panel yang dibuka pertama. Kalau tidak diisi, diambil dari
        // `server.url`.
        CAPACITOR_ELECTRON_PANEL_PATH: panelPath,
    },
});

child.on('close', (code) => {
    process.exit(code ?? 0);
});

process.on('SIGINT', () => {
    child.kill();
});
