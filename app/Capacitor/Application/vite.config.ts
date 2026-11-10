import { defineConfig } from 'vite';

export default defineConfig({
  root: './src',
  build: {
    outDir: '../dist',
    minify: false,
    emptyOutDir: true,
  },
  // The bundled `dist/` is loaded from the app origin (capacitor://localhost on
  // Android/iOS, file:// under Electron), so every asset URL must be relative.
  // Without this, `./js/launcher.js` resolves against the wrong root.
  base: './',
});
