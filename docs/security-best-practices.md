# Security Best Practices â€” Wedding Flower Decorations CBIR

Dokumen ini menyumutikan praktik terbaik keamanan yang harus diimplementasikan
dan diikuti selama pengembangan, deployment, dan maintenance aplikasi
**Wedding Flower Decorations CBIR**.

> **Catatan:** Dokumen ini harus dibaca bersama dengan
> `docs/environment-configuration/environment-configuration.md` dan
> `docs/platform-support/platform-support.md`.

---

## 🔒 Konfigurasi Dasar

### 1. File Environment (.env)

| Praktik | Detail |
|---|---|
| **Jangan commit .env** | Selalu tambahkan `.env` ke `.gitignore`. File ini berisi rahasia dan kredensial. |
| **APP_KEY unik** | Generate dengan `php artisan key:generate`. Gunakan kunci yang berbeda per environment. |
| **APP_DEBUG=false** | Selalu `false` di production. Aktivasi hanya untuk debugging lokal. |
| **Cookie secure** | Atur `SESSION_SECURE_COOKIE=true` di production untuk menjaga cookie hanya via HTTPS. |
| **HSTS headers** | Tambahkan header `Strict-Transport-Security` di Nginx/Apache: `max-age=31536000; includeSubDomains` |
| **File .env.example** | Isi dengan *placeholder* saja, bukan nilai nyata. Gunakan untuk dokumentasi variabel yang dibutuhkan. |

```dotenv
# .env.example — hanya nama variabel, nilai placeholder
APP_NAME=Wedding Flower Decorations
APP_ENV=local
APP_KEY=base64:CHANGE_THIS_TO_A_UNIQUE_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_DATABASE=placeholder
DB_USERNAME=placeholder
DB_PASSWORD=placeholder

# Firebase — jangan ekspor kredensial yang nyata
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json

# Midtrans — gunakan sandbox di development
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxx
MIDTRANS_CLIENT_KEY=Pclient-key-xxxxx
MIDTRANS_IS_PRODUCTION=false

# Google OAuth
GOOGLE_CLIENT_ID=placeholder
GOOGLE_CLIENT_SECRET=placeholder
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

### 2. HTTPS & Secure Context

- **Semua production deployment harus menggunakan HTTPS**.
- WebRTC (`getUserMedia`) memerlukan secure context — hanya akan berjalan di HTTPS.
- Capacitor production harus memiliki `cleartext: false` di `capacitor.config.json`.
- Gunakan Let's Encrypt atau sertifikat SSL yang valid untuk domain produksi.

### 3. CSP (Content Security Policy)

Tambahkan header CSP di konfigurasi web server (Nginx/Apache) atau middleware Laravel:

```nginx
# Nginx example
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://*.midtrans.com; font-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';";
```

Atau dalam Laravel middleware:

```php
// app/Http/Middleware/CSPMiddleware.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    $response->headers->set('Content-Security-Policy', [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", 'https://*.google.com'],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'https:'],
        'connect-src' => ["'self'", 'https://*.midtrans.com', 'https://*.fcm.googleapis.com'],
        'font-src' => ["'self'"],
        'frame-src' => ["'self'"],
        'object-src' => ["'self'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
    ]);
    return $response;
}
```

---

## 👤 Autentikasi & Otorisasi

### 1. Password & Account Security

| Praktik | Detail |
|---|---|
| **Bcrypt rounds: 12** | Sudah disetel di `config/auth.php` dan migration User. |
| **Password strength** | Validasi minimal 8 karakter, kombinasi huruf/angka/symbol. |
| **Rate limiting** | Gunakan `throttle` middleware pada route auth: `login` max 3x per menit per IP. |
| **OTP expiration** | OTP tidak boleh melebihi 10 menit. Gunakan `expires_at` di database. |
| **Two-factor (TOTP)** | Opional: Integrasi dengan `pragmarx/google2fa` atau `simple-php-otp`. |
| **Login terakhir** | Trakait IP, kota, region, negara via `ip-api.com` (lihat `LoginEvent` di `AppServiceProvider`). |

### 2. Sanctum Token Auth (API)

| Praktik | Detail |
|---|---|
| **Token never expires** | Securitasnya dikelola melalui revocation on logout. |
| **Stateful domains** | Konfigurasi `SANCTUM_STATEFUL_DOMAINS` di `.env.web`. |
| **Token di header** | Gunakan `Authorization: Bearer {token}` untuk API calls. |
| **Logout revoke** | Pastikan `php artisan logout` melakukan revoke token. |

### 3. Guest Identity

| Praktik | Detail |
|---|---|
| **Guest rows excluded** | Filter pada `@guest.local` email domain untuk admin/user panel. |
| **Guest tidak memiliki role** | Gunakan `?->hasRole()` atau `chatUser()?->hasRole()`. |
| **Session key** | ID guest disimpan di session, tidak memerlukan login. |
| **Konversi ke user** | Saat guest login, migrasikan guest row ke user tabel jika perlu. |

---

## ðŸ›¡ï¸ Data & Privacy Protection

### 1. PPDK (Personal Data Protection)

| Data | Proteksi |
|---|---|
| **KTP/ID number** | Jangan simpan seluruh angka KTP. Simpan hash atau bagian terakhir saja. |
| **Alamat lengkap** | Enkripsi jika perlu disimpan long-term. |
| **Nomor WhatsApp** | Simpan dalam format E.162, gunakan masking di UI. |
| **Email user** | Gunakan verifikasi email (`email_verified_at`) sebelum memberikan akses penuh. |

### 2. Payment Data (Midtrans)

| Data | Proteksi |
|---|---|
| **Server key** | Hanya ada di server, tidak pernah di-export ke klien. |
| **Client key** | Boleh di-frontend, tapi hanya untuk pembuatan Snap token. |
| **Payment proofs** | Simpan path file, jangan ekspor ke storage publik tanpa izin. |
| **Signature verification** | Verifikasi SHA512: `order_id + status_code + gross_amount + server_key` sebelum update status. |

### 3. CBIR / Image Privacy

- Gambar yang di-upload di-cache dan di-processing oleh AI Core di port 5000.
- Pastikan AI Core server tidak menyimpan data pribadi pengguna.
- Gunakan hash-based caching untuk mencegah image yang sama di-index berulang.

---

## 🌐 Network & API Security

### 1. API Endpoints

| Praktik | Detail |
|---|---|
| ** Sanctum guard** | Gunakan untuk semua API endpoint yang membutuhkan autentikasi. |
| **Throttling per user** | Limit request per menit per user agar mencegah brute force. |
| **Input validation** | Selalu gunakan Form Request validation, jangan mengandalkan input langsung dari user. |
| **Mass assignment protection** | Gunakan `$fillable` dan `$guarded` di model. Jangan gunakan `fill()` tanpa guarded. |
| **API rate limiting** | Konfigurasi di `routes/api.php` dengan `throttle:api` middleware. |

### 2. Webhooks (Midtrans, Fonnte)

| Praktik | Detail |
|---|---|
| **Signature verification** | Verifikasi SHA512 signature untuk semua webhook dari Midtrans dan Fonnte. |
| **Idempotency** | Handle idempotent request — jangan proses dua kali yang sama. |
| **Timeout** | Set timeout request ke webhook agar tidak block server. |
| **Logging** | Log incoming webhook (tanpa data rahasia) untuk audit trail. |

### 3. Database Security

| Praktik | Detail |
|---|---|
| **Migration testing** | Selalu testing migrasi di environment testing (SQLite). |
| **Soft deletes** | Gunakan `SoftDeletes` di model yang appropriate. |
| **Query scoping** | Gunakan Eloquent scopes untuk filter yang sering digunakan. |
| **Avoid N+1** | Gunakan `with()` untuk eager loading, monitoring dengan `php artisan tinker` + `query log`. |
| **Database indexes** | Pastikan kolom yang sering didai memiliki index. |

---

## 📦 Dependencies & Supply Chain

### 1. Composer Packages

| Praktik | Detail |
|---|---|
| **Lock file** | Selalu commit `composer.lock` untuk build yang dapat direproduksi. |
| **Version constraints** | Gunakan `^` atau `~` untuk pembaruan aman, hindari `*` atau `>=` tanpa alasan. |
| **Dependency audit** | Jalankan `composer audit` berkala untuk memeriksa kerentanan known. |
| **Jangan gunakan `--ignore-platform-req`** | Ini menyembunyikan ketidaksesuaian, pakai `composer req` yang bener. |

### 2. Node.js Packages

| Praktik | Detail |
|---|---|
| **Lock file** | Commit `package-lock.json` atau `yarn.lock`. |
| **Vulnerability scan** | Jalankan `npm audit` berkala. |
| **Transitive dependencies** | Pantau versi indirect dependencies yang digunakan. |

---

## 🔍 Testing Security

### 1. Security Test Cases

| Test Type | Contoh |
|---|---|
| **Authentication bypass** | Coba akses route terproteksi tanpa token/login. |
| **Mass assignment** | Coba kirim field tambahan via API dan cek apakah tersimpan. |
| **CSRF token** | Test form tanpa CSRF token diblokir. |
| **Rate limiting** | Coba login gagal berulang dan cek respons 429. |
| **Input injection** | Test SQL injection, XSS pada field input. |
| **File upload** | Test upload file dengan ekstensi ganjal dan ukuran melewati batas. |

### 2. Test Commands

```bash
# Test rate limiting
php artisan route:list | grep -E "login|register"

# Test CSRF
php artisan route:middleware | grep csrf

# Security header check (production)
curl -I https://domain-anda.com | grep -i "content-security-policy\|strict-transport-security"
```

---

## ðŸ› ï¸ Operational Security

### 1. Deployment Checklist

Sebelum deployment produksi:

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` di-set unik (bukan dari example)
- [ ] HTTPS aktif dengan sertifikat valid
- [ ] CSP header terpasang
- [ ] `SANCTUM_STATEFUL_DOMAINS` di-set
- [ ] Database backup terbaru
- [ ] Media storage dikonfigurasi (S3 atau local dengan izin)
- [ ] Environment variables checked (tidak ada nilai placeholder)
- [ ] Queue workers configured
- [ ] Scheduler configured
- [ ] `.env` added ke `.gitignore`
- [ ] Fitur yang aktif/disable sesuai kebutuhan (`PLATFORM_MODE`, `VITE_PLATFORM`)

### 2. Incident Response

- **Kompromi kredensial**: Rotate segera `APP_KEY`, `DB_PASSWORD`, `MIDTRANS_SERVER_KEY`, Firebase credentials.
- **Unauthorized access**: Review log akses, check audit trail, check guest identity usage.
- **Data leak**: Identifikasi data yang leak, quick backup, notify affected users jika perlu.
- **Jalur komunikasi**: Tatap maret ke tim teknisi, catat semua langkah pemulihan.

### 3. Regular Security Audits

- Bulan sekali: Review `.env` file, cek credential yang tidak digunakan.
- Setiap deployment: Jalankan `composer audit`, `npm audit`.
- Setiap major release: Security review code changes, test penetration basic.

---

## 📚 Referensi & Alat

| Alat | Digunakan untuk |
|---|---|
| **OWASP Top 10** | Referensi utama kerentanan web. |
| **Snyk** | Scanner kerentanan dependency. |
| **GitGuardian** | Deteksi secret di repository. |
| **Laravel Pint** | Code style yang konsisten (termasuk security-related formatting). |
| **PHPStan** | Static analysis untuk mendeteksi potensi bug. |
| **Rector** | Code refactoring untuk safety. |

---

## ðŸ™ Kontribusi

Jika Anda menemukan kerentanan keamanan atau ingin menambahkan praktik terbaik,
buat issue di repositori atau kirim pull request dengan pembaruan dokumentasi ini.

**Hak Cipta (c) 2026 Anugerah Ahmad Fachrurochim.**
Bagian dari dokumentasi lengkap aplikasi Wedding Flower Decorations CBIR.