# Deployment shell desktop (Electron)

Shell desktop adalah aplikasi Electron yang memuat server Laravel melalui
`BrowserWindow`. Tidak ada PHP atau database di dalam installer.

## Target shell

| Folder | Panel | appId | URL produksi |
|---|---|---|---|
| `app/Capacitor/DekstopApp` | storefront/akun `welcome` | `id.dekorasi.pengantin.user` | `https://domain/home` |
| `app/Capacitor/DekstopAdminApp` | admin | `id.dekorasi.pengantin.admin` | `https://domain/admin` |

Setiap shell memiliki `electron/main.ts`, `electron/tsconfig.json`,
`electron/electron-builder.config.js`, dan `electron/package.json`.

## Prasyarat

- Node.js LTS/npm dan dependency shell;
- Windows untuk build `.exe`;
- macOS + Xcode untuk build `.dmg` dan signing/notarization;
- Authenticode atau Developer ID untuk distribusi publik;
- server Laravel HTTPS yang sudah deploy.

## Siapkan server dan shell

Dari root:

```bash
composer install --no-dev --optimize-autoloader
npm ci
cp .env.desktop.example .env.desktop
npm run build:desktop
php artisan migrate --force
php artisan optimize
php artisan serve:desktop --host=127.0.0.1 --port=8002
```

Di shell:

```bash
cd app/Capacitor/DekstopApp
npm install
npm --prefix electron install
npm run set:url -- https://dekorasi.example.com/home
npm run desktop
```

Untuk admin, gunakan `app/Capacitor/DekstopAdminApp` dan URL `/admin`.
`set:url` memperbarui konfigurasi Capacitor serta `src/js/launcher.js`.

## Build Windows

```powershell
cd app/Capacitor/DekstopApp
npm run sync
npm --prefix electron run build
npx electron-builder --config electron/electron-builder.config.js --win
```

Paket tanpa installer:

```powershell
npx electron-builder --config electron/electron-builder.config.js --win --dir
```

MSIX:

```powershell
npx electron-builder --config electron/electron-builder.config.js --win --target=msix
```

## Build macOS

```bash
cd app/Capacitor/DekstopApp
npm run sync
npm --prefix electron run build
npx electron-builder --config electron/electron-builder.config.js --mac
```

Paket unpacked memakai `--mac --dir`. Distribusi publik memerlukan Developer ID,
hardened runtime, dan notarization Apple. Simpan certificate/password sebagai
secret CI.

## Output dan konfigurasi

`electron-builder.config.js` mendefinisikan `appId`, `productName`, output `dist`,
assets, dan file runtime. Output biasanya berada di `electron/dist` ketika
builder dijalankan dari project Electron; nama file mengikuti `productName` dan
`version`. Verifikasi output aktual, jangan mengasumsikan nama installer.

Target dan appId harus konsisten:

- user: `id.dekorasi.pengantin.user`;
- admin: `id.dekorasi.pengantin.admin`.

## Keamanan

- produksi harus HTTPS;
- origin tepercaya pada main process tidak boleh dilemahkan;
- jangan memuat URL arbitrary;
- jangan memasukkan secret server ke JavaScript shell;
- tandatangani installer sebelum distribusi;
- uji `/home`, `/signin`, `/messages`, dan `/admin` sesuai shell.

## Checklist rilis

```text
? build:desktop berhasil dan public/build/desktop/manifest.json ada
? server.url menunjuk HTTPS dan path panel yang benar
? npm run sync dan TypeScript berhasil
? installer diuji pada mesin bersih
? signing/notarization berhasil bila distribusi publik
? login, upload, chat, kamera, dan notifikasi diuji
? installer, credential, dan private key tidak masuk commit
```

## Troubleshooting

**Jendela putih:** pastikan server hidup, URL bisa dibuka dengan `curl`, path
`/home` atau `/admin` valid, dan TLS/CSP tidak memblokir halaman.

**Electron keluar:** install dependency `electron/`, ulangi
`npm --prefix electron run build`, lalu lihat log main process.

**Asset 404:** jalankan `npm run build:desktop` dari root dan pastikan server mode
desktop membaca manifest `public/build/desktop`.

## Referensi

- [Kompilasi aset](../../asset-compilation/asset-compilation.md)
- [Environment](../../environment-configuration/environment-configuration.md)
- [Deployment mobile](../mobile/mobile.md)
- `app/Capacitor/DekstopApp/README.md`
