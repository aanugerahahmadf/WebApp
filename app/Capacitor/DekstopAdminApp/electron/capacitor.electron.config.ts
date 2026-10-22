import { defineConfig } from '@capawesome/capacitor-electron/config';

export default defineConfig({
  /**
   * Content-Security-Policy untuk dokumen yang dimuat di dalam jendela.
   *
   * Runtime menyuntik policy ini ke header respons frame utama, jadi policy
   * ini berlaku untuk SELURUH halaman panel yang dibuka di jendela --
   * bukan hanya halaman Electron.
   *
   * Policy bawaan runtime (DEFAULT_DEV_CSP) punya `img-src 'self' data:
   * blob:`. Itu terlalu ketat untuk aplikasi ini: avatar profil bisa
   * berasal dari luar (pravatar.cc, ui-avatars.com) dan foto katalog pun
   * kadang menunjuk CDN. Semua img itu jadi gambar rusak -- persis yang
   * terlihat di halaman Messages.
   *
   * `font-src` juga diberi https: karena panel memakai webfont dari Bunny
   * Fonts (AppFont), dan tanpa itu huruf fallback yang dipakai.
   *
   * `connect-src` sudah longgar di policy bawaan (https: wss: ws: http:),
   * jadi polling Livewire dan notifikasi tetap jalan.
   */
  csp: {
    policy: [
      "default-src 'self'",
      // Google Identity Services dimuat dari accounts.google.com. Tanpa
      // host itu di sini, "Continue With Google" gagal dengan "Gagal
      // memuat Google Identity Services."
      "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://accounts.google.com",
      "style-src 'self' 'unsafe-inline'",
      "img-src 'self' data: blob: https: http:",
      "font-src 'self' data: https:",
      "media-src 'self' blob: https:",
      "connect-src 'self' https: wss: ws: http:",
      // Pop-up Google One Tap / token client hidup di frame Google.
      "frame-src 'self' https://accounts.google.com",
      "object-src 'none'",
      "base-uri 'self'",
    ].join('; '),

    devPolicy: [
      "default-src 'self'",
      "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://accounts.google.com",
      "style-src 'self' 'unsafe-inline'",
      "img-src 'self' data: blob: https: http:",
      "font-src 'self' data: https:",
      "media-src 'self' blob: https:",
      "connect-src 'self' https: wss: ws: http:",
      "frame-src 'self' https://accounts.google.com",
      "object-src 'none'",
      "base-uri 'self'",
    ].join('; '),
  },

  window: {
    // UKURAN APLIKASI DESKTOP, bukan ukuran ponsel.
    //
    // Panel admin adalah aplikasi meja kerja: tabel katalog, grafik
    // pendapatan, dan tiga tabel berdampingan (kategori -> paket ->
    // katalog) semuanya butuh lebar nyata. Jendela 380px hanya berguna
    // untuk menguji tata letak seluler, dan itu bukan tujuan shell ini.
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    backgroundColor: '#ffffff',
    // Ukuran & posisi jendela disimpan, jadi aplikasi reopen di tempat
    // yang sama seperti ditutup -- perilaku standar aplikasi desktop.
    statePersistence: true,
  },

  // Layar splash saat jendela mulai dimuat. Path relatif terhadap
  // direktori `electron/` ini.
  splashScreen: {
    path: 'assets/splash.html',
    width: 480,
    height: 320,
    backgroundColor: '#4f46e5',
    minimumDurationMs: 600,
  },

  // CATATAN: `server.url` SENGAJA TIDAK ditulis di file ini.
  //
  // Runtime @capawesome/capacitor-electron v0.1.1 membaca
  // `server.url` dari `../capacitor.config.json` (lewat
  // `generated/capacitor.config.json`) dan memakainya sebagai URL yang
  // dimuat DI DALAM jendela. Android dan iOS juga memakai `server.url`
  // yang sama, jadi satu file config melayani tiga platform.
  //
  // Yang 처리 "halaman mana yang dibuka pertama" ada di main.ts, bukan
  // di sini: nilai `server.url` dipaksa jadi origin supaya navigasi
  // antar panel tetap di dalam jendela.
});
