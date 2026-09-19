# Web Production Deployment Guide

This guide covers building, configuring, and deploying the Laravel Wedding Organizer CBIR application for web production environments.

---

## Production Build

The web platform uses a dedicated npm script that sets `VITE_PLATFORM=web` before invoking Vite. This ensures only the web-specific JavaScript entry point (`resources/js/app-web/app-web.js`) is bundled, keeping the production asset as small as possible.

```bash
npm run build:web
```

The command is equivalent to:

```bash
cross-env VITE_PLATFORM=web vite build
```

Built assets are written to `public/build/web/` with a `manifest.json` that Laravel uses to map entry-point paths to their hashed filenames.

---

## Required Environment Variables

Create a `.env` file in the project root with at least the following values before running the deployment checklist.

```dotenv
# Application
APP_ENV=production
APP_KEY=base64:...           # Required — generate with: php artisan key:generate
APP_URL=https://yourdomain.com  # Must match your actual domain exactly

# Session
SESSION_DRIVER=cookie        # Or "database" for multi-server setups

# Frontend platform
VITE_PLATFORM=web

# Database (example — adjust for your DB engine)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=dbuser
DB_PASSWORD=secret

# Cache & Queue (recommended for production)
CACHE_STORE=redis
QUEUE_CONNECTION=database

# Mail, Firebase, Midtrans, etc.
# ... add all remaining secrets from your base .env
```

### Variable Reference

| Variable | Required | Description |
|---|---|---|
| `APP_ENV` | Yes | Must be `production` to disable debug output |
| `APP_KEY` | Yes | 32-byte base64 key used for encryption and signing |
| `APP_URL` | Yes | Full URL of the application — must match the actual domain |
| `SESSION_DRIVER` | Yes | `cookie` for single-server; `database` for multi-server |
| `VITE_PLATFORM` | Yes | Must be `web` so `PlatformAssetManager` loads the correct manifest |

> `APP_URL` is particularly sensitive — an incorrect value breaks URL generation, email links, and Sanctum CSRF protection.

---

## Deployment Checklist

Run these commands in order after uploading the application code to the server.

```bash
# 1. Install PHP dependencies (production-only, no dev packages)
composer install --no-dev --optimize-autoloader

# 2. Build frontend assets for the web platform
npm ci
npm run build:web

# 3. Cache Laravel configuration, routes, and views for performance
php artisan optimize

# 4. Run pending database migrations
php artisan migrate --force
```

### Notes

- `composer install --no-dev` excludes testing and development packages, reducing disk usage and attack surface.
- `npm ci` uses the exact versions locked in `package-lock.json` — prefer this over `npm install` in CI/CD pipelines.
- `php artisan optimize` is a convenience command that combines `config:cache`, `route:cache`, and `view:cache`.
- The `--force` flag on `migrate` bypasses the interactive confirmation prompt required in production mode.

If you use a deployment tool (Envoyer, Deployer, Forge), add these four commands to your deployment hook in the listed order.

---

## Nginx Configuration

The following example configures Nginx with PHP-FPM (via FastCGI), proper asset caching, and `FollowSymlinks`-equivalent support via `root` resolution.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;

    # Redirect all HTTP traffic to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # TLS certificates (example: Let's Encrypt / Certbot)
    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    # Document root — Laravel's public/ directory
    # Using the real path avoids issues with symlinks in deployment pipelines
    root /var/www/yourdomain.com/public;
    index index.php;
    charset utf-8;

    # ── Logging ──────────────────────────────────────────────────────────
    access_log /var/log/nginx/yourdomain.com.access.log;
    error_log  /var/log/nginx/yourdomain.com.error.log;

    # ── Static asset caching ─────────────────────────────────────────────
    # Vite produces content-hashed filenames, so these can be cached
    # indefinitely — a new filename is generated on every build.
    location ~* ^/build/(web|mobile|desktop)/.+\.(js|css|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)$ {
        expires max;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options nosniff;
        access_log off;
        try_files $uri =404;
    }

    # Generic static file caching for everything else under public/
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot|webp)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }

    # ── Laravel front controller ─────────────────────────────────────────
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── PHP-FPM (FastCGI) ────────────────────────────────────────────────
    location ~ \.php$ {
        # Pass requests to PHP-FPM via Unix socket (adjust path as needed)
        fastcgi_pass   unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;

        # Standard FastCGI parameters
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param  DOCUMENT_ROOT   $realpath_root;

        # Recommended timeouts for long-running requests (e.g. image processing)
        fastcgi_read_timeout 120;
        fastcgi_buffers      16 16k;
        fastcgi_buffer_size  32k;
    }

    # ── Security: deny access to hidden files ────────────────────────────
    location ~ /\.(?!well-known) {
        deny all;
    }

    # ── File upload size (adjust to match php.ini) ───────────────────────
    client_max_body_size 20M;
}
```

### Symlink Support

When using zero-downtime deployment tools (Envoyer, Capistrano, Deployer), the `current/` directory is typically a symlink pointing to the latest release. Nginx resolves the `root` path through symlinks automatically because it evaluates `$realpath_root` at request time — no additional configuration is needed.

If Nginx is configured with `disable_symlinks on` (unusual), change it to `disable_symlinks if_not_owner` or `off`.

---

## Apache Configuration (.htaccess)

Place the following `.htaccess` file in the `public/` directory. Laravel ships with a default `.htaccess`; the example below extends it with production-appropriate caching and security headers.

```apache
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    # Follow symbolic links (required for deployment symlinks)
    Options +FollowSymLinks

    # Handle Laravel front controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# ── HTTPS redirect ────────────────────────────────────────────────────────
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# ── Immutable caching for Vite content-hashed assets ─────────────────────
<IfModule mod_expires.c>
    ExpiresActive On

    # Vite hashed bundles: cache forever (filename changes on rebuild)
    <FilesMatch "^.+\.(js|css)\?id=.+$">
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </FilesMatch>

    # Font and image assets
    <FilesMatch "\.(woff2?|ttf|eot|otf|svg|png|jpg|jpeg|gif|ico|webp)$">
        ExpiresDefault "access plus 30 days"
        Header set Cache-Control "public, max-age=2592000"
    </FilesMatch>
</IfModule>

# ── Security headers ──────────────────────────────────────────────────────
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Remove server signature
    Header unset Server
    Header always unset X-Powered-By
</IfModule>

# ── Deny access to sensitive files ────────────────────────────────────────
<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Required Apache Modules

Ensure the following modules are enabled:

```bash
a2enmod rewrite
a2enmod expires
a2enmod headers
systemctl restart apache2
```

---

## Post-Deployment Verification

After completing the deployment checklist, verify the deployment is healthy:

```bash
# Check application status and active platform
php artisan about

# Confirm the web platform is detected correctly
php artisan platform:status

# Verify asset manifest exists
ls -la public/build/web/manifest.json

# Confirm there are no config caching issues
php artisan config:show app.env
```

---

## Rolling Back

If a deployment needs to be rolled back:

```bash
# Clear all caches (required before switching to a previous release)
php artisan optimize:clear

# Restore the previous release (deployment-tool specific)
# e.g. Envoyer: mark a previous deployment as current
# e.g. Deployer: dep rollback production

# Re-run optimize after switching releases
php artisan optimize
```

---

## Related Documentation

- [Asset Compilation](../asset-compilation.md) — how `npm run build:web` works internally
- [Environment Configuration](../environment-configuration.md) — full variable reference and multi-platform strategy
- `.env.web.example` — annotated starter file for web environment variables
