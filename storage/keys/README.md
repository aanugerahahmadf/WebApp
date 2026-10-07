# Firebase Service Account (Private Key)

**JANGAN COMMIT FILE INI!** Sudah di-gitignore.

## Cara dapatkan:

1. Buka [Firebase Console](https://console.firebase.google.com/)
2. Pilih project: `wedding-flower-decorasi`
3. Project Settings (⚙️) → Service Accounts
4. Klik **"Generate new private key"**
5. Simpan file JSON di sini sebagai: `firebase-service-account.json`

## Format file yang diharapkan:

```json
{
  "type": "service_account",
  "project_id": "wedding-flower-decorasi",
  "private_key_id": "xxx",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-xxx@wedding-flower-decorasi.iam.gserviceaccount.com",
  "client_id": "xxx",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-xxx%40wedding-flower-decorasi.iam.gserviceaccount.com",
  "universe_domain": "googleapis.com"
}
```

## Konfigurasi .env:

```env
FIREBASE_CREDENTIALS=storage/keys/firebase-service-account.json
FIREBASE_STORAGE_BUCKET=wedding-flower-decorasi.firebasestorage.app
FIREBASE_STORAGE_PATH_PREFIX=uploads
```

## Install driver GCS:

```bash
composer require superbalist/laravel-google-cloud-storage
```

## Test sync:

```bash
# Dry-run (laporan saja)
php artisan firebase:sync

# Upload ke Firebase
php artisan firebase:sync --apply

# Upload + hapus lokal (free storage lokal)
php artisan firebase:sync --apply --delete-local
```

## Auto-sync via Scheduler:

Sudah dikonfigurasi di `app/Console/Kernel/Kernel.php`:
- Harian jam 04:00: `firebase:sync --apply --delete-local`
- Log: `storage/logs/scheduler-firebase-sync.log`