# Dekorasi — Aplikasi Desktop (Electron)

Shell desktop untuk **storefront** (panel `welcome`) dan **panel akun**.

Jendelanya membungkus server Laravel yang sama seperti aplikasi web — tidak
ada PHP di dalam Electron. Panel dirender oleh server, Electron hanya
jendelanya.

> **Catatan:** panel `user` sudah dilebur ke panel `welcome` (lihat
> `bootstrap/providers.php`). Jadi shell ini sebenarnya menjalankan SATU
> panel: `welcome` di root domain — storefront tamu, halaman auth
> (`/signin`, `/signup`, `/auth`, OTP), dan seluruh halaman akun.

```
app/Capacitor/DekstopApp/
├── src/                    # halaman peluncur (fallback, bukan entry point Electron)
├── electron/
│   ├── main.ts             # main process: origin tepercaya + halaman pertama
│   ├── capacitor.electron.config.ts   # ukuran jendela, splash screen, CSP
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
cd app/Capacitor/DekstopApp
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
| `npm run desktop -- --panel /messages` | jalankan, tapi buka panel tertentu |
| `npm run sync` | build + `cap sync @capawesome/capacitor-electron` saja |
| `npm run desktop:pack` | build + `electron-builder` (installer .exe / .dmg) |
| `npm run set:url -- <url>` | geser server tujuan (ditulis ke `capacitor.config.json` **dan** `src/js/launcher.js`) |
| `npm run android` / `npm run ios` | build untuk HP |

## Yang perlu diketahui

### Halaman yang dibuka

`server.url` di `capacitor.config.json` adalah sumber kebenaran. Native app
pertama-tama membuka `/app-loading?target=...`, lalu melakukan warm-up request
dan mengarahkan pengguna ke halaman target:

```json
"server": { "url": "http://127.0.0.1:8000/app-loading?target=%2Fhome" }
```

Desktop, Android, dan iOS semuanya memakai nilai yang sama. Gunakan
`npm run set:url -- <url-target>` untuk mengubah target; loading screen tetap
dipasang otomatis di depan target tersebut.

### Kenapa `main.ts` menimpa `CAPACITOR_ELECTRON_DEV_SERVER_URL`

Runtime `@capawesome/capacitor-electron` memakai variabel itu sebagai dua
hal sekaligus: URL yang dimuat, **dan** daftar origin tepercaya
(`url.startsWith(devServerUrl)`).

Kalau nilainya berisi path panel (`.../home`), URL panel lain — `/user`
dari menu akun, `/admin` dari panel admin, `/signin` dari halaman auth —
keluar dari prefix itu, lalu guard runtime melemparnya ke browser sistem.
Praktisnya: begitu pengguna logout atau menekan "Buka Panel Admin", jendela
yang terbuka adalah browser — bukan aplikasi.

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

### Content-Security-Policy

`electron/capacitor.electron.config.ts` mengoverride CSP bawaan runtime.
Policy bawaan punya `img-src 'self' data: blob:`, dan itu terlalu ketat:
avatar profil bisa berasal dari luar (pravatar.cc, ui-avatars.com), jadi
avatarnya tampil sebagai gambar rusak. Policy di file itu mengizinkan
gambar dan font dari luar, seperti website.

### Avatar profil

Avatar diambil dari kolom `avatar_url`, yang bisa berisi path relatif, URL
absolut ke host produksi, atau URL pihak ketiga. Semuanya diterjemahkan
menjadi URL yang bisa dimuat dari server yang sedang melayani request
lewat `App\Support\AvatarUrl\AvatarUrl`.

###_packaging / icon

`electron/assets/` adalah folder build resources electron-builder. Letakkan
`icon.ico` (Windows), `icon.icns` (macOS), dan `icon.png` (Linux) di sana.
