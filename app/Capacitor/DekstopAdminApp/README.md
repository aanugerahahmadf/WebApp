# Dekorasi Admin — Aplikasi Desktop (Electron)

Shell desktop untuk **panel admin** Filament (`/admin`).

Jendelanya membungkus server Laravel yang sama seperti aplikasi web — tidak
ada PHP di dalam Electron. Panel dirender oleh server, Electron hanya
jendelanya.

```
app/Capacitor/DekstopAdminApp/
├── src/                    # halaman peluncur (fallback, bukan entry point Electron)
├── electron/
│   ├── main.ts             # main process: origin tepercaya + halaman pertama
│   ├── capacitor.electron.config.ts   # ukuran jendela, splash screen
│   └── assets/             # icon + splash untuk electron-builder
├── scripts/
│   ├── run-desktop.mjs     # build -> sync -> compile -> jalankan
│   └── set-url.mjs         # geser server tujuan
└── capacitor.config.json   # server.url = panel yang dibuka
```

## Menjalankan

```bash
# 1. Server Laravel harus hidup dulu
cd ../../..
php artisan serve --host=127.0.0.1 --port=8000

# 2. Jalankan aplikasi desktop
cd app/Capacitor/DekstopAdminApp
npm install
npm --prefix electron install
npm run desktop
```

`npm run desktop` melakukan semuanya: build bundle web, sync ke platform
Electron, compile `electron/main.ts`, lalu membuka jendela.

## Perintah lain

| Perintah | Arti |
|---|---|
| `npm run desktop` | build + sync + jalankan jendela |
| `npm run sync` | build + `cap sync @capawesome/capacitor-electron` saja |
| `npm run desktop:pack` | build + `electron-builder` (installer .exe / .dmg) |
| `npm run set:url -- <url>` | geser server tujuan (ditulis ke `capacitor.config.json` **dan** `src/js/launcher.js`) |
| `npm run android` / `npm run ios` | build untuk HP |

## Yang perlu diketahui

### Halaman yang dibuka

`server.url` di `capacitor.config.json` adalah sumber kebenaran:

```json
"server": { "url": "http://127.0.0.1:8000/admin/signin" }
```

Desktop, Android, dan iOS semuanya memakai nilai yang sama. Desktop membuka
`/admin/signin` langsung — aplikasi admin selalu butuh login dulu.

### Kenapa `main.ts` menimpa `CAPACITOR_ELECTRON_DEV_SERVER_URL`

Runtime `@capawesome/capacitor-electron` memakai variabel itu sebagai dua
hal sekaligus: URL yang dimuat, **dan** daftar origin tepercaya
(`url.startsWith(devServerUrl)`).

Kalau nilainya berisi path panel (`.../admin/signin`), URL panel lain —
`/home` dari Sign Out — keluar dari prefix itu, lalu guard runtime
melemparnya ke browser sistem. Praktisnya: satu-satunya jalan keluar dari
aplikasi adalah menutup jendela.

Jadi `main.ts` memaksa variabel itu jadi **origin saja**, dan path panelnya
dipisah ke `panelPath`. Halaman pertama baru diarahkan ke `panelPath` setelah
URL-nya benar-benar stabil (lihat komentar di `main.ts`).

### Kenapa `npm run desktop` tidak memakai `cap run`

CLI paket `@capawesome/capacitor-electron` mengecek dev server dengan
`fetch()` lalu **tidak** menutup body responsnya. Saat
`AbortSignal.timeout(3000)` menyala, undici melempar `AssertionError` yang
tidak tertangani dan proses CLI mati dengan kode `3221226505` — **setelah**
jendelanya sudah terbuka. `scripts/run-desktop.mjs` melakukan langkah yang
sama tanpa fetch yang bocor itu.

###打包 / icon

`electron/assets/` adalah folder build resources electron-builder. Letakkan
`icon.ico` (Windows), `icon.icns` (macOS), dan `icon.png` (Linux) di sana.
