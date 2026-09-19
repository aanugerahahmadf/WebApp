#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  Deployment Script — Wedding Flower Decorations (Linux VPS)
#  Run from the Laravel project root on your VPS
# ═══════════════════════════════════════════════════════════════════

set -e

echo ""
echo "============================================"
echo " Wedding Flower Decorations — Deploy Script"
echo "============================================"
echo ""

# ── 1. Check .env.production ──────────────────────────────────────
if [ ! -f ".env.production" ]; then
    echo "[ERROR] .env.production not found!"
    echo "Copy .env.production and fill in your values."
    exit 1
fi

# ── 2. Copy production env ────────────────────────────────────────
echo "[1/8] Copying .env.production to .env ..."
cp -f .env.production .env

# ── 3. Install dependencies ───────────────────────────────────────
echo "[2/8] Installing composer dependencies ..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 4. Generate application key ──────────────────────────────────
echo "[3/8] Generating application key ..."
php artisan key:generate --force

# ── 5. Run migrations ────────────────────────────────────────────
echo "[4/8] Running database migrations ..."
php artisan migrate --force

# ── 6. Cache configs ─────────────────────────────────────────────
echo "[5/8] Caching configurations ..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 7. Create storage link ───────────────────────────────────────
echo "[6/8] Creating storage symlink ..."
php artisan storage:link

# ── 8. Set permissions ───────────────────────────────────────────
echo "[7/8] Setting permissions ..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── 9. Restart queue workers ─────────────────────────────────────
echo "[8/8] Restarting queue workers ..."
php artisan queue:restart

echo ""
echo "============================================"
echo " Deployment Complete!"
echo "============================================"
echo ""
echo " Backend API: $(grep APP_URL .env | cut -d'=' -f2)"
echo ""
echo " Next steps:"
echo " 1. Update .env with your actual values"
echo " 2. Configure nginx to point to /public"
echo " 3. Build Flutter: flutter build apk --dart-define-from-file=.env.production"
echo ""
