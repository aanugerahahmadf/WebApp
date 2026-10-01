import { defineConfig } from '@capawesome/capacitor-electron/config';

export default defineConfig({
  window: {
    width: 1280,
    height: 860,
    // Deliberately small: a phone-sized window is the quickest way to review the
    // mobile layout of the panel that the Capacitor mobile shell uses.
    minWidth: 380,
    minHeight: 560,
    backgroundColor: '#ffffff',
    statePersistence: true,
  },

  // NOTE: `server.url` is deliberately NOT set here.
  //
  // @capawesome/capacitor-electron v0.1.1 always loads the bundled `dist/`
  // (`mainWindow.loadURL(devServerUrl ?? `${appOrigin}/`)`) and ignores the
  // `server` block from capacitor.config.json. So on desktop the bundled
  // `src/index.html` is the entry point, and `src/js/launcher.js` forwards the
  // WebView to the Laravel panel.
  //
  // Android and iOS behave the opposite way: they honour `server.url` in
  // capacitor.config.json and never paint `dist/`. The two platforms therefore
  // reach the same panel through two different mechanisms, and `PANEL_URL` in
  // src/js/launcher.js is kept in sync with `server.url` by
  // `npm run set:url -- <panel-url>`.

  // A splash screen is shown automatically while the app boots when a splash
  // file exists (`assets/splash.html` or `assets/splash.png`). Uncomment to
  // customize it:
  // splashScreen: {
  //   path: 'assets/splash.html',
  //   width: 400,
  //   height: 300,
  //   backgroundColor: '#ffffff',
  //   minimumDurationMs: 0,
  // },

  // Per-plugin config overrides, merged over the `plugins` section of
  // capacitor.config.json (this section wins per key). Being TypeScript, values
  // can be computed:
  // import packageJson from '../package.json';
  // plugins: {
  //   LiveUpdate: { defaultChannel: `production-${packageJson.version}` },
  // },
});
