@echo off
REM ═══════════════════════════════════════════════════════════════════
REM  Deployment Script — Wedding Flower Decorations
REM  Run this from the project root on the production server
REM ═══════════════════════════════════════════════════════════════════

echo.
echo ============================================
echo  Wedding Flower Decorations — Deploy Script
echo ============================================
echo.

REM ── 1. Check if .env.production exists ────────────────────────────
if not exist ".env.production" (
    echo [ERROR] .env.production not found!
    echo Copy .env.production and fill in your values.
    pause
    exit /b 1
)

REM ── 2. Copy production env ────────────────────────────────────────
echo [1/8] Copying .env.production to .env ...
copy /Y .env.production .env

REM ── 3. Install dependencies ───────────────────────────────────────
echo [2/8] Installing composer dependencies ...
call composer install --no-dev --optimize-autoloader --no-interaction

REM ── 4. Generate application key ──────────────────────────────────
echo [3/8] Generating application key ...
php artisan key:generate --force

REM ── 5. Run migrations ────────────────────────────────────────────
echo [4/8] Running database migrations ...
php artisan migrate --force

REM ── 6. Cache configs ─────────────────────────────────────────────
echo [5/8] Caching configurations ...
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

REM ── 7. Create storage link ───────────────────────────────────────
echo [6/8] Creating storage symlink ...
php artisan storage:link

REM ── 8. Set permissions ───────────────────────────────────────────
echo [7/8] Setting permissions ...
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)F" /T /Q >nul 2>&1
icacls "bootstrap/cache" /grant "IIS_IUSRS:(OI)(CI)F" /T /Q >nul 2>&1

REM ── 9. Verify ────────────────────────────────────────────────────
echo [8/8] Verifying deployment ...
php artisan about --only="Environment,Cache,Database" 2>nul

echo.
echo ============================================
echo  Deployment Complete!
echo ============================================
echo.
echo  Backend API: %APP_URL%
echo  Admin Panel: %ADMIN_URL%
echo.
echo  Next steps:
echo  1. Update .env with your actual values
echo  2. Configure nginx/Apache to point to /public
echo  3. Build Flutter: flutter build apk --dart-define-from-file=.env.production
echo.
pause
