#!/bin/sh
set -e

# Wait for the MySQL service to be ready (service name from env, default "mysql")
DB_HOST="${DB_HOST:-mysql}"
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306} ..."
if command -v mysqladmin >/dev/null 2>&1; then
    until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --silent 2>/dev/null; do
        echo "MySQL not ready - retrying..."
        sleep 2
    done
else
    sleep 10
fi
echo "MySQL is ready."

# Optional: run migrations/seeders when AUTO_MIGRATE is truthy (default off)
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
