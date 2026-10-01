/**
 * Launcher for the User Capacitor shell.
 *
 * The user panel is server rendered by Laravel, so the real UI lives at
 * `server.url` in capacitor.config.json. This file only runs when the bundled
 * `dist/` is painted: Electron at boot, or a failed remote load. It either
 * forwards the WebView to the panel or shows a retry UI.
 *
 * Keep PANEL_URL in sync with capacitor.config.json:
 *   npm run set:url -- http://10.0.2.2:8000/welcome
 */

/** @type {string} Panel root, i.e. `server.url` from capacitor.config.json. */
const PANEL_URL = 'http://10.0.2.2:8000/welcome';

/** How long to wait for the panel before showing the retry UI. */
const PROBE_TIMEOUT_MS = 12000;

const titleEl = document.getElementById('boot-title');
const statusEl = document.getElementById('boot-status');
const spinnerEl = document.getElementById('boot-spinner');
const actionsEl = document.getElementById('boot-actions');
const retryEl = document.getElementById('boot-retry');

/** Set once the WebView has been handed to the panel, so we stop arming timers. */
let handedOff = false;

/**
 * Forward the WebView to the panel, carrying a one-shot marker so the server
 * knows the request came from the shell. AppPlatform also detects the shell
 * from the User-Agent and the `app_shell` cookie it writes on the panel itself,
 * so this parameter is only a fast path for the very first request.
 */
function openPanel() {
    const target = new URL(PANEL_URL);

    target.searchParams.set('__shell', 'native');

    handedOff = true;

    window.location.replace(target.toString());
}

function showError(message) {
    if (spinnerEl) spinnerEl.hidden = true;
    if (statusEl) statusEl.textContent = message;
    if (actionsEl) actionsEl.hidden = false;
}

async function probe() {
    if (!navigator.onLine) {
        showError('Tidak ada koneksi internet.');

        return;
    }

    try {
        // HEAD is enough: any 2xx/3xx/4xx proves the backend is reachable. A 5xx
        // still means "the server answered", so only a network-level throw is
        // treated as unreachable here.
        await fetch(PANEL_URL, { method: 'HEAD', mode: 'no-cors', cache: 'no-store' });
    } catch (error) {
        // Fall through to the redirect anyway: the WebView may succeed where
        // fetch() was blocked by CORS, and a real error page beats a dead end.
    }

    openPanel();
}

window.addEventListener('offline', () => showError('Koneksi terputus. Periksa jaringan Anda.'));

retryEl?.addEventListener('click', () => {
    if (actionsEl) actionsEl.hidden = true;
    if (spinnerEl) spinnerEl.hidden = false;
    if (statusEl) statusEl.textContent = 'Memuat...';

    probe();
});

if (titleEl) titleEl.textContent = 'Dekorasi';

// Never leave the splash screen spinning forever.
setTimeout(() => {
    if (!handedOff) {
        showError('Server belum merespons. Periksa alamat server.');
    }
}, PROBE_TIMEOUT_MS);

probe();
