#!/bin/sh
set -e

# Render sets PORT; Apache must listen on it.
PORT="${PORT:-80}"

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your Render environment." >&2
    exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

# Bind Apache to Render's assigned port.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache --no-ansi
php artisan route:cache --no-ansi
php artisan view:cache --no-ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-ansi
fi

if [ "$1" = "apache" ] || [ "$1" = "serve" ]; then
    exec apache2-foreground
fi

exec "$@"
