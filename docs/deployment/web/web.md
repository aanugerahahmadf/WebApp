# Panduan Deployment Produksi Web

Panduan ini mencakup proses membangun, mengkonfigurasi, dan men-deploy aplikasi Laravel Wedding Organizer CBIR untuk lingkungan produksi web.

---

## Build Produksi

Platform web menggunakan skrip npm khusus yang menetapkan `VITE_PLATFORM=web` sebelum memanggil Vite. Ini memastikan hanya titik masuk JavaScript khusus web (`resources/js/app-web/app-web.js`) yang dibundel, sehingga aset produksi sekecil mungkin.

```bash
npm run build:web
```

Perintah ini setara dengan:

```bash
cross-env VITE_PLATFORM=web vite build
```

Aset yang dibangun ditulis ke `public/build/web/` dengan `manifest.json` yang digunakan Laravel untuk memetakan jalur titik masuk ke nama file yang di-hash.

---

## Variabel Lingkungan yang Wajib

Buat file `.env` di root proyek dengan setidaknya nilai-nilai berikut sebelum menjalankan daftar periksa deployment.

```dotenv
# Aplikasi
APP_ENV=production
APP_KEY=base64:...           # Wajib — buat dengan: php artisan key:generate
APP_URL=https://yourdomain.com  # Harus cocok persis dengan domain Anda

# Session
SESSION_DRIVER=cookie        # Atau "database" untuk setup multi-server

# Platform frontend
VITE_PLATFORM=web

# Database (contoh — sesuaikan untuk engine DB Anda)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=dbuser
DB_PASSWORD=secret

# Cache & Queue (direkomendasikan untuk produksi)
CACHE_STORE=redis
QUEUE_CONNECTION=database

# Mail, Firebase, Midtrans, dll.
# ... tambahkan semua rahasia lainnya dari base .env Anda
```

### Referensi Variabel

| Variabel | Wajib | Deskripsi |
|---|---|---|
| `APP_ENV` | Ya | Harus `production` untuk menonaktifkan output debug |
| `APP_KEY` | Ya | Kunci base64 32-byte yang digunakan untuk enkripsi dan penandatanganan |
| `APP_URL` | Ya | URL lengkap aplikasi — harus cocok dengan domain yang sebenarnya |
| `SESSION_DRIVER` | Ya | `cookie` untuk server tunggal; `database` untuk multi-server |
| `VITE_PLATFORM` | Ya | Harus `web` agar `PlatformAssetManager` memuat manifest yang benar |

> `APP_URL` sangat sensitif — nilai yang salah akan merusak pembuatan URL, tautan email, dan perlindungan CSRF Sanctum.

---

## Daftar Periksa Deployment

Jalankan perintah-perintah ini secara berurutan setelah mengunggah kode aplikasi ke server.

```bash
# 1. Instal dependensi PHP (hanya produksi, tanpa paket dev)
composer install --no-dev --optimize-autoloader

# 2. Build aset frontend untuk platform web
npm ci
npm run build:web

# 3. Cache konfigurasi Laravel, rute, dan tampilan untuk performa
php artisan optimize

# 4. Jalankan migrasi database yang tertunda
php artisan migrate --force
```

### Catatan

- `composer install --no-dev` mengecualikan paket pengujian dan pengembangan, mengurangi penggunaan disk dan permukaan serangan.
- `npm ci` menggunakan versi yang terkunci persis di `package-lock.json` — lebih disarankan daripada `npm install` dalam pipeline CI/CD.
- `php artisan optimize` adalah perintah praktis yang menggabungkan `config:cache`, `route:cache`, dan `view:cache`.
- Flag `--force` pada `migrate` melewati prompt konfirmasi interaktif yang diperlukan dalam mode produksi.

Jika Anda menggunakan alat deployment (Envoyer, Deployer, Forge), tambahkan keempat perintah ini ke deployment hook Anda dalam urutan yang tercantum.

---

## Konfigurasi Nginx

Contoh berikut mengkonfigurasi Nginx dengan PHP-FPM (via FastCGI), caching aset yang tepat, dan dukungan setara `FollowSymlinks` melalui resolusi `root`.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;

    # Redirect semua traffic HTTP ke HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # Sertifikat TLS (contoh: Let's Encrypt / Certbot)
    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    # Document root — direktori public/ Laravel
    # Menggunakan path sebenarnya menghindari masalah dengan symlink dalam pipeline deployment
    root /var/www/yourdomain.com/public;
    index index.php;
    charset utf-8;

    # ── Logging ──────────────────────────────────────────────────────────
    access_log /var/log/nginx/yourdomain.com.access.log;
    error_log  /var/log/nginx/yourdomain.com.error.log;

    # ── Caching aset statis ─────────────────────────────────────────────
    # Vite menghasilkan nama file content-hashed, sehingga dapat di-cache
    # selamanya — nama file baru dibuat pada setiap build.
    location ~* ^/build/(web|mobile|desktop)/.+\.(js|css|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)$ {
        expires max;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options nosniff;
        access_log off;
        try_files $uri =404;
    }

    # Caching file statis generik untuk semua yang ada di bawah public/
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot|webp)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }

    # ── Front controller Laravel ─────────────────────────────────────────
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── PHP-FPM (FastCGI) ────────────────────────────────────────────────
    location ~ \.php$ {
        # Teruskan request ke PHP-FPM melalui Unix socket (sesuaikan path sesuai kebutuhan)
        fastcgi_pass   unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;

        # Parameter FastCGI standar
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param  DOCUMENT_ROOT   $realpath_root;

        # Timeout yang direkomendasikan untuk request yang berjalan lama (mis. pemrosesan gambar)
        fastcgi_read_timeout 120;
        fastcgi_buffers      16 16k;
        fastcgi_buffer_size  32k;
    }

    # ── Keamanan: tolak akses ke file tersembunyi ────────────────────────
    location ~ /\.(?!well-known) {
        deny all;
    }

    # ── Ukuran upload file (sesuaikan dengan php.ini) ───────────────────
    client_max_body_size 20M;
}
```

### Dukungan Symlink

Saat menggunakan alat deployment zero-downtime (Envoyer, Capistrano, Deployer), direktori `current/` biasanya merupakan symlink yang menunjuk ke rilis terbaru. Nginx me-resolve jalur `root` melalui symlink secara otomatis karena mengevaluasi `$realpath_root` pada waktu request — tidak diperlukan konfigurasi tambahan.

Jika Nginx dikonfigurasi dengan `disable_symlinks on` (tidak umum), ubah menjadi `disable_symlinks if_not_owner` atau `off`.

---

## Konfigurasi Apache (.htaccess)

Tempatkan file `.htaccess` berikut di direktori `public/`. Laravel sudah menyertakan `.htaccess` default; contoh di bawah ini memperluasnya dengan caching yang sesuai untuk produksi dan header keamanan.

```apache
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    # Ikuti symbolic links (diperlukan untuk symlink deployment)
    Options +FollowSymLinks

    # Tangani front controller Laravel
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# ── Redirect HTTPS ────────────────────────────────────────────────────────
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# ── Caching immutable untuk aset content-hashed Vite ─────────────────────
<IfModule mod_expires.c>
    ExpiresActive On

    # Bundle hashed Vite: cache selamanya (nama file berubah saat rebuild)
    <FilesMatch "^.+\.(js|css)\?id=.+$">
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </FilesMatch>

    # Aset font dan gambar
    <FilesMatch "\.(woff2?|ttf|eot|otf|svg|png|jpg|jpeg|gif|ico|webp)$">
        ExpiresDefault "access plus 30 days"
        Header set Cache-Control "public, max-age=2592000"
    </FilesMatch>
</IfModule>

# ── Header keamanan ──────────────────────────────────────────────────────
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Hapus signature server
    Header unset Server
    Header always unset X-Powered-By
</IfModule>

# ── Tolak akses ke file sensitif ────────────────────────────────────────
<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Modul Apache yang Diperlukan

Pastikan modul-modul berikut diaktifkan:

```bash
a2enmod rewrite
a2enmod expires
a2enmod headers
systemctl restart apache2
```

---

## Verifikasi Pasca-Deployment

Setelah menyelesaikan daftar periksa deployment, verifikasi bahwa deployment berjalan dengan baik:

```bash
# Periksa status aplikasi dan platform yang aktif
php artisan about

# Konfirmasi platform web terdeteksi dengan benar
php artisan platform:status

# Verifikasi manifest aset ada
ls -la public/build/web/manifest.json

# Konfirmasi tidak ada masalah caching konfigurasi
php artisan config:show app.env
```

---

## Rollback

Jika deployment perlu di-rollback:

```bash
# Bersihkan semua cache (diperlukan sebelum beralih ke rilis sebelumnya)
php artisan optimize:clear

# Pulihkan rilis sebelumnya (spesifik per alat deployment)
# mis. Envoyer: tandai deployment sebelumnya sebagai current
# mis. Deployer: dep rollback production

# Jalankan ulang optimize setelah beralih rilis
php artisan optimize
```

---

## Dokumentasi Terkait

- [Kompilasi Aset](../../asset-compilation/asset-compilation.md) — cara kerja `npm run build:web` secara internal
- [Konfigurasi Lingkungan](../../environment-configuration/environment-configuration.md) — referensi variabel lengkap dan strategi multi-platform
- `.env.web.example` — file starter beranotasi untuk variabel lingkungan web
