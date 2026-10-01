/**
 * Point this Capacitor shell at a Laravel backend.
 *
 *   npm run set:url -- http://10.0.2.2:8000/admin
 *   npm run set:url -- https://dekorasi.example.com/admin
 *
 * Writes the URL to both places that need it:
 *   - capacitor.config.json  -> `server.url`, used by Android and iOS
 *   - src/js/launcher.js      -> `PANEL_URL`, used by the Electron fallback
 *
 * The trailing slash is stripped so `new URL(PANEL_URL)` in the launcher never
 * produces a double slash, and the panel path is preserved as-is.
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const raw = (process.argv[2] ?? '').trim().replace(/\/+$/, '');

if (!raw) {
    console.error('Usage: npm run set:url -- <panel-url>\nExample: npm run set:url -- http://10.0.2.2:8000/admin');

    process.exit(1);
}

if (!/^https?:\/\//i.test(raw)) {
    console.error(`\`${raw}\` is not an http(s) URL. Use the full origin plus the panel path, e.g. http://10.0.2.2:8000/admin`);

    process.exit(1);
}

const configPath = resolve(appRoot, 'capacitor.config.json');
const launcherPath = resolve(appRoot, 'src/js/launcher.js');

// ── capacitor.config.json ───────────────────────────────────────────────────
const config = JSON.parse(readFileSync(configPath, 'utf8'));

config.server = { ...(config.server ?? {}), url: raw };

// Plain http only works against a dev host; production must be https so the
// WebView gets a secure context (service workers, camera, secure cookies).
const isLocalDev = /^http:\/\//i.test(raw);

config.server.cleartext = isLocalDev;

if (isLocalDev) {
    config.server.androidScheme = 'http';
    config.server.iosScheme = 'http';
} else {
    config.server.androidScheme = 'https';
    config.server.iosScheme = 'https';
}

writeFileSync(configPath, `${JSON.stringify(config, null, 2)}\n`, 'utf8');

// ── src/js/launcher.js ─────────────────────────────────────────────────────
const launcher = readFileSync(launcherPath, 'utf8');
const updated = launcher.replace(
    /const PANEL_URL = '[^']*';/,
    `const PANEL_URL = '${raw}';`,
);

if (updated === launcher) {
    console.error('Could not find `const PANEL_URL = ...` in src/js/launcher.js — update it by hand.');

    process.exit(1);
}

writeFileSync(launcherPath, updated, 'utf8');

console.log(`Panel URL set to ${raw}`);
console.log(isLocalDev
    ? 'Cleartext http enabled — fine for a LAN/emulator dev host only.'
    : 'https enforced.');
console.log('Next: npm run build && npx cap sync');
